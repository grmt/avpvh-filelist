// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

var eeSFL_isTouchscreen = false;
var eeSFL_ListID = 1;
var eeSFL_FileID = false;
var eeSFL_CheckEmail = false;
var eeSFL_FileDateAdded = false;
var eeSFL_FileDateChanged = false;

// Scroll down to #eeSFL_FileListTop
function eeSFL_ScrollToIt() {

	jQuery('html, body').animate({ scrollTop: jQuery('#eeSFL_FileListTop').offset().top }, 1000);

	return false;

}