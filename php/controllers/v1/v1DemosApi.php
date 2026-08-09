<?php
/*
 Demo Api controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class v1DemosApiController extends BaseApiController {
    public FwModel|Demos $model;
    public string $model_name = 'Demos';
    public string $base_url = '/v1/demosapi';

    public string $required_fields = ''; #required fields
    public string $save_fields = ''; #fields allowed to modify
    public string $list_sortdef = 'dateAdded desc';   //default sorting - request param name, asc|desc direction
    public array $list_sortmap = array( //sorting map: request param name => sql field name(s) asc|desc direction
                                        'dateAdded' => 'add_time',
                                        'status'    => 'status',
    );

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction(): ?array {
        $this->initFilter();
        $this->setListSorting();

        $rows = $this->model->listByWhere([
            'status' => $this->db->opNOT(FwModel::STATUS_DELETED)
        ], $this->list_filter['limit'], $this->list_filter['offset'], $this->list_orderby);

        $ps = [
            self::LIST_ITEMS_NAME => $this->model->filterListForJson($rows),
            self::META_NAME       => [
                self::LIST_COUNT_NAME      => $this->model->getCount(), #this is total records, not rows
                self::LIST_LIMIT_NAME      => $this->list_filter['limit'],
                self::LIST_OFFSET_NAME     => $this->list_filter['offset'],
                self::LIST_SORT_FIELD_NAME => $this->list_filter['sortby'],
                self::LIST_SORT_ORDER_NAME => $this->list_filter['sortdir'],
            ]
        ];
        return ['_json' => $ps];
    }

    public function ShowAction($icode): array {
        $item = $this->model->oneByIcodeOrFail($icode);
        $item = $this->model->filterForJson($item);

        $ps = [
            self::ITEM_NAME => $item
        ];
        return ['_json' => $ps];
    }

    /**
     * @throws ApplicationException
     * @throws DBException
     * @throws ValidationException
     */
    public function SaveAction($icode): array {
        $item = $this->fw->postedJson;
        if (empty($this->save_fields)) {
            throw new Exception("No fields to save defined, define in Controller.save_fields");
        }

        $this->Validate($icode, $item);

        $item_old = $this->model->oneByIcodeOrFail($icode);
        $id       = $item_old['id'] ?? 0;

        $itemdb = $this->getSaveFields($id, $item);
        $id     = $this->modelAddOrUpdate($id, $itemdb);

        #return new/updated record
        $ps = [
            self::ITEM_NAME => $this->model->oneForJson($id)
        ];
        return ['_json' => $ps];
    }

    /**
     * Validate input before save
     * @throws ValidationException
     */
    public function Validate(string $icode, array $item): void {
        $result = $this->validateRequired($icode, $item, $this->required_fields);

        //        if ($result) {
        //            #do additional validation and throw new ValidationException if needed
        //        }
        $this->validateCheckResult();
    }

    /**
     * delete record
     * @param $icode
     * @return array
     * @throws DBException
     * @throws NotFoundException
     */
    public function DeleteAction($icode): array {
        $item = $this->model->oneByIcodeOrFail($icode);

        $this->model->delete($item['id']);

        return ['_json' => []];
    }

}//end of class
