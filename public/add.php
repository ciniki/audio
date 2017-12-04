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
// tnid:     The ID of the tenant to add the audio to.
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_audio_add(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'url'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'URL'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'audio', 'private', 'checkAccess');
    $rc = ciniki_audio_checkAccess($ciniki, $args['tnid'], 'ciniki.audio.add'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.audio');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Check to see if a url was provide to an audio
    //
    if( isset($args['url']) && $args['url'] != '' ) {
        //
        // Add the audio into the database
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'audio', 'private', 'insertFromURL');
        $rc = ciniki_audio_insertFromURL($ciniki, $args['tnid'], 
            $args['url'], basename($args['url']), 'no');
        // If a duplicate audio is found, then use that id instead of uploading a new one
        if( $rc['stat'] != 'ok' && $rc['stat'] != 'exists' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.27', 'msg'=>'Internal Error', 'err'=>$rc['err']));
        }
        if( !isset($rc['id']) ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.28', 'msg'=>'Invalid file type'));
        }
        $audio_id = $rc['id'];

    }

    else {
        //
        // Check to see if an audio was uploaded
        //
        if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.29', 'msg'=>'Upload failed, file too large.'));
        }
        // FIXME: Add other checkes for $_FILES['uploadfile']['error']

        //
        // Check for a uploaded file
        //
        if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['tmp_name'] == '' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.30', 'msg'=>'Upload failed, no file specified.'));
        }
        $uploaded_file = $_FILES['uploadfile']['tmp_name'];

        //
        // Add the audio into the database
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'audio', 'private', 'insertFromUpload');
        $rc = ciniki_audio_insertFromUpload($ciniki, $args['tnid'], 
            $_FILES['uploadfile'], $_FILES['uploadfile']['name'], 'no');
        // If a duplicate audio is found, then use that id instead of uploading a new one
        if( $rc['stat'] != 'ok' && $rc['stat'] != 'exists' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.31', 'msg'=>'Internal Error', 'err'=>$rc['err']));
        }
        if( !isset($rc['id']) ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.audio');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.audio.32', 'msg'=>'Invalid file type'));
        }
        $audio_id = $rc['id'];
    }

    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.audio');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok', 'id'=>$audio_id);
}
?>
