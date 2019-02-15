<?php
/*
Reports base model class
*/

class Reports extends FwModel {
    public $report_code='';
    public $format='';  #report format, if empty - html, other options: html, csv, pdf, xls
    public $f=array();  #report filters/options
    #render options for html to pdf/xls/etc... convertor
    public $render_options=array(
        'cmd' => '--page-size Letter --print-media-type', #--quiet
        'landscape' => true,
    );

    public function __construct() {
        parent::__construct();

        #$this->table_name = '';
    }

    public function cleanupRepcode($repcode){
        return strtolower(preg_replace("[^\w-]", "", $repcode));
    }

    /**
     * Convert report code into class name
     * @param  string $repcode 'abc-something-summary'
     * @return string          'AbcSomethingSummary'
     */
    public function repcodeToClass($repcode){
        $result='';

        $pieces = explode('-', $repcode);
        foreach ($pieces as $piece) {
            $result .= ucfirst($piece);
        }

        return $result;
    }

    /**
     * Create instance of report class by repcode
     * @param  string $repcode cleaned report code
     * @param  array $f       filters passed from request
     * @return instance       instance of report class
     */
    public function createInstance($repcode, $f){
        $report_class_name = $this->repcodeToClass($repcode);
        if (!$report_class_name) throw new ApplicationException('Wrong Report Code');

        $class_file = dirname(__FILE__).'/Reports/'.$report_class_name.'.php';
        if (!file_exists($class_file)) throw new ApplicationException('No Report Class found for the Report Code');

        require_once($class_file);

        $full_class_name = 'Report'.$report_class_name;
        $report = new $full_class_name($this->fw);
        $report->init($repcode, $f);

        return $report;
    }

    /**
     * init specific report instance
     * @param  string $value [description]
     * @return [type]        [description]
     */
    public function init($repcode, $f){
        $this->report_code = $repcode;
        $this->f = $f;
        $this->format = $f['format'];
    }

    #override in specific report class
    public function getReportFilters(){
        return $this->f;
    }

    #override in specific report class
    public function getReportData(){
        return array();
    }

    #override in specific report class
    public function saveChanges(){
    }


    # for pdf - use wkhtmltopdf or Dompdf https://github.com/dompdf/dompdf (install via composer)
    # for docx - use VsWord https://github.com/vench/vsword
    # for xls - just html with .xlsx
    # for csv required - $ps['rep']['rows'], $ps['rep']['headers']
    public function render($ps){
        $common_dir = '/admin/reports/common';
        $base_dir = '/admin/reports/'.strtolower($this->report_code);

        switch ($this->format) {
            case 'pdf':
                ini_set("memory_limit", "512M");
                $filename=$this->report_code.'-'.date('Ymd', time()); #Ymd-His

                $ps['f']['edit']=false; #force any edit modes off
                $ps['IS_PRINT_MODE']=true;
                $ps['IS_EXPORT_PDF']=true;

                $html = parse_page($base_dir, $this->fw->config->PAGE_LAYOUT_PRINT, $ps, 'v');
                #$html = parse_page($base_dir, $common_dir.'/docx.html', $ps, 'v');

                if ($this->fw->config->PDF_CONVERTER){
                   ### if wkhtmltopdf
                   $tmp_file = Utils::getTmpFilename().'.html';
                   $out_file = Utils::getTmpFilename().'.pdf';
                   #logger("tmp files: $tmp_file, $out_file");
                   file_put_contents($tmp_file, $html);

                   $orient = $this->render_options['landscape'] ? '--orientation Landscape' : '';
                   $cmd=$this->fw->config->PDF_CONVERTER." ".$this->render_options['cmd']." $orient file:///$tmp_file $out_file";
                   #logger("cmd: [$cmd]");
                   system($cmd);

                   $disposition = 'attachment';
                   header("Content-type: application/pdf");
                   header("Content-Disposition: $disposition; filename=\"$filename\"");

                   readfile($out_file); //read file content and output to browser

                   // unlink($tmp_file);
                   // unlink($out_file);

                }else{
                    ### if Dompdf
                    require_once $this->fw->config->SITE_ROOT.'/php/dompdf/autoload.inc.php';
                    #use Dompdf\Dompdf;
                    $dompdf = new Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    if ($this->render_options['landscape']) {
                        $dompdf->setPaper('A4', 'landscape');
                    }else{
                        $dompdf->setPaper('A4', 'portrait');
                    }
                    $dompdf->render();
                    $dompdf->stream($filename);
                }
                break;

            case 'docx':
                $ps['IS_PRINT_MODE']=true;
                $ps['IS_EXPORT_DOC']=true;

                ini_set('display_errors', '0'); #disable "Strict Standards" errors in VsWord
                error_reporting(0);
                require_once $this->fw->config->SITE_ROOT.'/php/vsword/VsWord.php';
                VsWord::autoLoad();

                $html = parse_page($base_dir, $common_dir.'/docx.html', $ps, 'v');

                $doc = new VsWord();
                $parser = new HtmlParser($doc);
                $parser->parse($html);
                $tmpfname = Utils::getTmpFilename();
                $doc->saveAs($tmpfname);

                $filename=$this->report_code.'-'.date('Ymd', time()).'.docx'; #Ymd-His
                $disposition = 'attachment';
                //output to browser
                header('Content-type: application/msword');
                header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');

                readfile($tmpfname); //read file content and output to browser
                //echo $html;

                unlink($tmpfname);
                break;

            case 'xls':
                $ps['IS_PRINT_MODE']=true;
                $ps['IS_EXPORT_XLS']=true;

                $html = parse_page($base_dir, $common_dir.'/xls.html', $ps, 'v');

                $filename=$this->report_code.'-'.date('Ymd', time()).'.xlsx'; #Ymd-His
                $disposition = 'attachment';
                //output to browser
                header('Content-type: application/vnd.ms-excel');
                header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');

                echo $html;
                break;

            case 'csv':
                $filename=$this->report_code.'-'.date('Ymd', time()).'.csv'; #Ymd-His
                Utils::responseCSV($ps['rep']['rows'], $ps['rep']['headers'], $filename);
                break;

            default: #html - show report using templates from related report dir
                $this->fw->parser($base_dir, $ps);
                break;
        }
    }

    #misc common report utils

    #add "perc" value for each row (percentage of row's "ctr" from sum of all ctr)
    public function _calcPerc(&$rows, $ctr_field='ctr', $perc_field='perc'){
        $total_ctr = 0;
        foreach ($rows as $row) {
            $total_ctr += $row[$ctr_field];
        }
        if ($total_ctr>0){
            foreach ($rows as $row) {
                $row[$perc_field] += $row[$ctr_field] / $total_ctr * 100;
            }
            unset($row);
        }
        return $total_ctr;
    }

}

?>