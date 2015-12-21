<?php
//
// Description
// -----------
// This function will insert an audio file from the file system
//
// Arguments
// ---------
// ciniki:
// business_id:		The ID of the business the photo is attached to.
//
// 
// Returns
// -------
// The audio ID that was added.
//
function ciniki_audio_hooks_insertFromFile(&$ciniki, $business_id, $args) { //$upload_file, $name, $force_duplicate) {
    
    if( !isset($args['filename']) ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2915', 'msg'=>'Missing file'));
    }

	$extension = preg_replace("/^.*\.([^\.]+)$/", "$1", $args['filename']);
	if( !isset($args['name']) || $args['name'] == '' ) {
		$args['name'] = preg_replace("/^.*\/([^\/]+)(\.[^\.]+)$/", "$1", $args['filename']);
	}
    if( !isset($args['original_filename']) || $args['original_filename'] == '' ) {
        $args['original_filename'] = preg_replace("/^.*\/([^\/]+)(\.[^\.]+)$/", "$1", $args['filename']);
    }

	$file = file_get_contents($args['filename']);

	$checksum = crc32($file);

	//
	// Get the type of audio file
	//
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime_type = $finfo->file($args['filename']);
	$type = 0;
	if( $mime_type == 'application/octet-stream' ) {
		switch($extension) {
			case 'ogg': $type = 20; break;
			case 'wav': $type = 30; break;
			case 'mp3': $type = 40; break;
		}
	} else {
		switch($mime_type) {
			case 'application/ogg': $type = 20; break;
			case 'audio/ogg': $type = 20; break;
			case 'audio/wav': $type = 30; break;
			case 'audio/x-wav': $type = 30; break;
			case 'audio/mpeg': $type = 40; break;
		}
	}
	if( $type == 0 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2916', 'msg'=>'Invalid format. ' . $mime_type . ', ' . $extension));
	}

	//
	// Add code to check for duplicate file based on crc
	//
	$strsql = "SELECT id, title "
        . "FROM ciniki_audio "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND checksum = '" . ciniki_core_dbQuote($ciniki, $checksum) . "' ";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.audio', 'audio');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2917', 'msg'=>'Unable to check for duplicates', 'err'=>$rc['err']));
	}

	//
	// Check if there is an image that exists, and that the force flag has not been set
	//
	if( isset($rc['audio']) && (!isset($args['force_duplicate']) || $args['force_duplicate'] != 'yes') ) {
		// Return the ID incase the calling script wants to use the existing image
		return array('stat'=>'exists', 'id'=>$rc['audio']['id'], 'err'=>array('pkg'=>'ciniki', 'code'=>'2918', 'msg'=>'Duplicate file'));
	}

	//
	// Get the business UUID
	//
	$strsql = "SELECT uuid "
        . "FROM ciniki_businesses "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' ";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.businesses', 'business');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['business']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2919', 'msg'=>'Unable to get business details'));
	}

	$business_uuid = $rc['business']['uuid'];

	//
	// Get a new UUID
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.audio');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$uuid = $rc['uuid'];

	//
	// Move the file to ciniki-storage
	//
	$storage_dirname = $ciniki['config']['ciniki.core']['storage_dir'] . '/'
		. $business_uuid[0] . '/' . $business_uuid
		. '/ciniki.audio/'
		. $uuid[0];
	$storage_filename = $storage_dirname . '/' . $uuid;
	if( !is_dir($storage_dirname) ) {
		if( !mkdir($storage_dirname, 0700, true) ) {
			ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2920', 'msg'=>'Unable to add file'));
		}
	}
    //
    // Use copy so it will work with ssh2.sftp file systems
    //
    if( !copy($args['filename'], $storage_filename) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2921', 'msg'=>'Unable to add file'));
    }

	//
	// Setup the object
	//
	$object_args = array(
		'uuid'=>$uuid,
		'parent_id'=>0,
		'type'=>$type,
		'original_filename'=>$args['original_filename'],
		'title'=>$args['name'],
		'checksum'=>$checksum,
		);

	//
	// Add the object
	// 
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
	$rc = ciniki_core_objectAdd($ciniki, $business_id, 'ciniki.audio.file', $object_args);

	return array('stat'=>'ok', 'id'=>$rc['id']);
}
?>
