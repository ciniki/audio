<?php
//
// Description
// ===========
// This function will add a new audio into the audio database.  The audio
// data should be posted as a file upload.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to add the audio to.
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_audio_download(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'audio_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Audio File'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'audio', 'private', 'checkAccess');
    $rc = ciniki_audio_checkAccess($ciniki, $args['business_id'], 'ciniki.audio.download'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

	//
	// Get the uuid for the file
	//
	$strsql = "SELECT ciniki_businesses.uuid AS business_uuid, ciniki_audio.uuid AS file_uuid, "
		. "ciniki_audio.title AS name, ciniki_audio.type, ciniki_audio.original_filename "
		. "FROM ciniki_audio, ciniki_businesses "
		. "WHERE ciniki_audio.id = '" . ciniki_core_dbQuote($ciniki, $args['audio_id']) . "' "
		. "AND ciniki_audio.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND ciniki_businesses.id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.audio', 'file');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['file']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'1852', 'msg'=>'Unable to find file'));
	}
	$filename = $rc['file']['original_filename'];
	$file_uuid = $rc['file']['file_uuid'];
	$business_uuid = $rc['file']['business_uuid'];

	//
	// Move the file into storage
	//
	$storage_dirname = $ciniki['config']['ciniki.core']['storage_dir'] . '/'
		. $business_uuid[0] . '/' . $business_uuid 
		. '/ciniki.audio/'
		. $file_uuid[0];
	$storage_filename = $storage_dirname . '/' . $file_uuid;
	error_log('--' . $storage_filename . '--');
	$finfo = finfo_open(FILEINFO_MIME);
	if( $finfo ) { error_log('Content-Type: ' . finfo_file($finfo, $storage_filename)); }
	if( !is_file($storage_filename) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'1888', 'msg'=>'Unable to find file'));
	}

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
	header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT"); 
	header('Cache-Control: no-cache, must-revalidate');
	header('Pragma: no-cache');
	// Set mime header
	$finfo = finfo_open(FILEINFO_MIME);
	if( $finfo ) { header('Content-Type: ' . finfo_file($finfo, $storage_filename)); }
	// Specify Filename
	header('Content-Disposition: attachment;filename="' . $filename . '"');
	header('Content-Length: ' . filesize($storage_filename));
	header('Cache-Control: max-age=0');

	error_log('Downloading: ' . $storage_filename);
	$fp = fopen($storage_filename, 'rb');
	fpassthru($fp);


	return array('stat'=>'ok');
}
?>
