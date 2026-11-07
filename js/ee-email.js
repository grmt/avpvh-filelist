// AV-PvH File List - File Sending, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

function eeSFLE_SendFile(eeSFL_FileID) {

	event.preventDefault(); // Don't follow the link

    var eeFileName = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' .eeSFL_RealFileName').text(); // Get the File Name

    jQuery('#eeSFLE_SendTheseFilesList em').text(eeFileName); // Add it to the list view

    console.log( 'Sending: ' + eeFileName + ' (ID: ' + eeSFL_FileID + ')' );

	jQuery('#eeSFLE_SendMoreFiles input[type=checkbox]').prop("checked", false); // Uncheck all the boxes

	jQuery('.eeSFLE_AddFileID_' + eeSFL_FileID + ' input[type=checkbox]').prop("checked", true); // Check the first file's box

	jQuery('#eeSFL_Modal_SendFiles').show();
}



// Open the File List
function eeSFLE_Send_AddMoreFiles() {

	event.preventDefault();

	jQuery('#eeSFLE_MessageDetials').slideUp(); // Make room for the list in the overlay

	jQuery('#eeSFLE_SendMoreFiles').slideDown();
}



// Cancel the File List
function eeSFLE_Send_AddMoreCancel() {

	event.preventDefault();

	jQuery('#eeSFLE_SendMoreFiles input[type=checkbox]').prop("checked", false); // Uncheck all the boxes

	jQuery('.eeSFLE_AddFileID_' + eeSFL_FileID + ' input[type=checkbox]').prop("checked", true); // Check the first file's box

	jQuery('#eeSFLE_SendMoreFiles').slideUp();

	jQuery('#eeSFLE_MessageDetials').slideDown();
}



// Approve Added Files
function eeSFLE_Send_AddTheseFiles() {

	event.preventDefault();
	var eeArray = new Array;

	jQuery('#eeSFLE_SendMoreFiles').slideUp();
	jQuery('#eeSFLE_MessageDetials').slideDown();

	// Add each to the list display
	jQuery('#eeSFLE_SendTheseFilesList em').text(''); // Reset

	jQuery('#eeSFLE_SendMoreFiles input[type=checkbox]').each(function() {

		if( jQuery(this).is(':checked') ) {

			var eeFileName = decodeURIComponent( jQuery(this).val() ); // Decode

			eeArray.push(eeFileName);
		}
	});

	var eeArrayLength = eeArray.length;
	var eeSendingThese = '';

	for(var i = 0; i < eeArrayLength; i++) {
		if(eeArray[i]) {
			eeSendingThese = eeSendingThese + eeArray[i] + ', ';
		}
	}

	// Strip last ,
	eeSendingThese = eeSendingThese.substring(0, eeSendingThese.length - 2);

	jQuery('#eeSFLE_SendTheseFilesList em').text(eeSendingThese);
}



// AJAX Post to File Moving Engine
function eeSFLE_DoFileSend() {

	event.preventDefault(); // Don't submit

	let eeFrom = jQuery('#eeSFL_SendFrom').val();
	let eeTo = jQuery('#eeSFL_SendTo').val();
	let eeCc = jQuery('#eeSFL_SendCc').val();
	let eeSubject = jQuery('#eeSFL_SendSubject').val();
	let eeMessage = jQuery('#eeSFL_SendMessage').val();
	let eeFileList = jQuery('#eeSFLE_SendTheseFilesList em').text();

	var eeFormData = {
		'id': eeSFL_ListID,
		'from': eeFrom,
		'to': eeTo,
		'cc': eeCc,
		'subject': eeSubject,
		'message': eeMessage,
		'files': eeFileList,
		'eeSecurity': eeSFL_SendNonce,
		'action': 'avpvh_flst_sendfile_job'
	};

	var eeSaved = jQuery('#SendFilesButton').html(); // Save the button
	jQuery('#SendFilesButton').html('<img class="eeFloatRight" src="' + eeSFL_SendingSpinnerURL + '" alt="Sending" />');

	console.log('Sending Files...');
	console.log(eesfl_vars.ajaxurl);
	console.log(eeFormData);

	jQuery.post(eesfl_vars.ajaxurl, eeFormData, function(response) {

		console.log(response);

		if(response == 'SUCCESS') {

			jQuery('#SendFilesButton').html(eeSaved); // Replace the button

			jQuery('.eeSFL_Modal').hide();

		} else {

			alert(response);
		}

	});

}







jQuery(document).ready(function() {

	// jQuery('#eeSFL_SendTheseFilesList').hide();
	jQuery('#eeSFLE_SendMoreFiles').hide();


}); // END Ready Function

