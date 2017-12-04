<?php
//
// Description
// -----------
// This function will insert an image which has been uploaded and parsed into
// the $_FILES section of PHP.  This means the form must be submitted with
// "application/x-www-form-urlencoded".
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant the photo is attached to.
//
//
// upload_file:     The array from $_FILES[upload_field_name].
//
// name:            *optional* The name to give the photo in the database.  If blank
//                  The $file['name'] is used as the name of the photo.
//
// force_duplicate: If this is set to 'yes' and the image crc32 checksum is found
//                  already belonging to this tenant, the image will still be inserted 
//                  into the database.
// 
// Returns
// -------
// The image ID that was added.
//
function ciniki_audio_insertFromUpload(&$ciniki, $tnid, $upload_file, $name, $force_duplicate) {
    $tmp_filename = $upload_file['tmp_name'];
    $original_filename = $upload_file['name'];
    $extension = preg_replace("/^.*\.([^\.]+)$/", "$1", $original_filename);
    if( $name == null || $name == '' ) {
        $name = $original_filename;

//      if( preg_match('/(IMG|DSC)_[0-9][0-9][0-9][0-9]\.(jpg|gif|tiff|bmp|png)/', $name, $matches) ) {
//          // Switch to blank name
//          $name = '';
//      }

        $name = preg_replace('/(.mp3|.wav|.ogg)/i', '', $name);
    }

    $file = file_get_contents($tmp_filename);

    $checksum = crc32($file);

    //
    // FIXME: Get the type of audio file
    //
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($upload_file['tmp_name']);
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.21', 'msg'=>'Invalid format. ' . $mime_type . ', ' . $extension));
    }

    //
    // Add code to check for duplicate file based on crc
    //
    $strsql = "SELECT id, title FROM ciniki_audio "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND checksum = '" . ciniki_core_dbQuote($ciniki, $checksum) . "' ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.audio', 'audio');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.22', 'msg'=>'Unable to check for duplicates', 'err'=>$rc['err']));
    }

    //
    // Check if there is an image that exists, and that the force flag has not been set
    //
    if( isset($rc['audio']) && $force_duplicate != 'yes' ) {
        // Return the ID incase the calling script wants to use the existing image
        return array('stat'=>'exists', 'id'=>$rc['audio']['id'], 'err'=>array('code'=>'ciniki.audio.23', 'msg'=>'Duplicate file'));
    }

    //
    // Get the tenant UUID
    //
    $strsql = "SELECT uuid FROM ciniki_tenants "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.tenants', 'tenant');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['tenant']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.24', 'msg'=>'Unable to get tenant details'));
    }

    $tenant_uuid = $rc['tenant']['uuid'];

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
        . $tenant_uuid[0] . '/' . $tenant_uuid
        . '/ciniki.audio/'
        . $uuid[0];
    $storage_filename = $storage_dirname . '/' . $uuid;
    if( !is_dir($storage_dirname) ) {
        if( !mkdir($storage_dirname, 0700, true) ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.25', 'msg'=>'Unable to add file'));
        }
    }
    if( !rename($_FILES['uploadfile']['tmp_name'], $storage_filename) ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.26', 'msg'=>'Unable to add file'));
}

    //
    // Setup the object
    //
    $args = array(
        'uuid'=>$uuid,
        'parent_id'=>0,
        'type'=>$type,
        'original_filename'=>$original_filename,
        'title'=>$name,
        'checksum'=>$checksum,
        );

    //
    // Add the object
    // 
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.audio.file', $args);

    return array('stat'=>'ok', 'id'=>$rc['id']);
}
?>
