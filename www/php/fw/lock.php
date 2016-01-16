<?php
/* 
 Functions for work with files using LOCKS for access

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com

# Apr'2005 - oSa - added seeks (to avoid file changes between open and flock)
*/

/*
 LOCK_SH
 LOCK_EX
 LOCK_NB
 LOCK_UN

 get_lockfile
 get_lockfile_arr
 add_lockfile
 clear_lockfile

 add_file_counter
*/

############## for locking
//function LOCK_SH()  { 1 }     #  Shared lock (for reading)
//function LOCK_EX()  { 2 }     #  Exclusive lock (for writing)
//function LOCK_NB()  { 4 }     #  Non-blocking request (don't stall)
//function LOCK_UN()  { 8 }     #  Free the lock (careful!)

#################### full file contents
function get_lockfile($filename, $clearafterall=0){  #clear after read?
 $result='';

 if ($clearafterall){
    $LFILE=fopen($filename,"r+b") or die("Can't open [$filename] file $!");
    flock($LFILE,LOCK_EX) or die("Can't write-lock [$filename] file"); #full lock (wait until somebody write/read file)
    $result = fread($LFILE, filesize($filename));
    ftruncate($LFILE,0);
 }
 else{
    $LFILE=fopen($filename,"rb") or die("Can't open [$filename] file $!");
    flock($LFILE,LOCK_SH) or die("Can't read-lock [$filename] file"); #read lock (wait until somebody write to file)
    $result = fread($LFILE, filesize($filename));
 }
 fclose($LFILE);
 return $result;
}

#################### return file contents as array of strings
function get_lockfile_arr($filename, $clearafterall=0){  #clear after read?
 $result=array();

 if ($clearafterall){
    $LFILE=fopen($filename,"r+b") or die("Can't open [$filename] file $!");
    flock($LFILE,LOCK_EX) or die("Can't write-lock [$filename] file"); #full lock (wait until somebody write/read file)
    $result = preg_split("/[\r\n]/", fread($LFILE, filesize($filename)) );
    ftruncate($LFILE,0);
 }
 else{
    $LFILE=fopen($filename,"rb") or die("Can't open [$filename] file $!");
    flock($LFILE,LOCK_SH) or die("Can't read-lock [$filename] file"); #read lock (wait until somebody write to file)
    $result = preg_split("/[\r\n]/", fread($LFILE, filesize($filename)) );
 }
 fclose($LFILE);
 return $result;
}

###################### add string to file
# $logfile=$_[0];
# $outstr=$_[1];
# $isReplace=$_[2];  #replace?
# $isBinary=$_[3];   #is binary (for win)
# $usemask=$_[4];  #uses umask 
function add_lockfile($logfile, $outstr, $isReplace='', $isBinary=1, $usemask=''){
 $savemask=0;

 $mode='a';
 if ($isReplace) $mode='w';
 if ($usemask) $savemask=umask($usemask);
 if ($isBinary) $mode.='b';

 $LFILE=fopen($logfile, $mode) or die("Can't open [$logfile] mode [$mode] file");
 flock($LFILE,LOCK_EX); #write_lock
 fseek($LFILE, 0, SEEK_END);   #rewind to end
 fwrite($LFILE,$outstr);
 fclose($LFILE);

 if ($usemask) umask($savemask);
}

###################### truncate file to empty file
function clear_lockfile($filename){

 $LFILE=fopen($filename,"w") or die("Can't open [$filename] file $!");
 flock($LFILE,LOCK_EX);
 ftruncate($LFILE,0);
 fclose(LFILE);
}


########################### add counter in the file
function add_file_counter($filename, $amount=1, $maxvalue=0){
 $count=0;

 if (!$amount && $maxvalue>=0) $amount=1;

 $FILE=fopen($filename, "r+") or die("Can't open [$filename] file $!");
 flock($FILE, LOCK_EX);
 $count = trim(fread($FILE, filesize($filename)));
 if (!$count) $count = 0;
 if ($maxvalue && ($count>$maxvalue)) $count = 0;
 $count+=$amount;
 fseek($FILE, 0, SEEK_SET);
 fwrite($FILE, "$count");
 ftruncate($FILE, ftell ($FILE));
 fclose ($FILE);

 return $count;
}

?>