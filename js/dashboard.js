function hideAllTabs() {
    $("#allSitesData").hide()
    $("#allSitesData-button").removeClass('active').addClass('nonactive');
    $("#mySiteData").hide();
    $("#mySiteData-button").removeClass('active').addClass('nonactive');
    $("#screening").hide();
    $("#screening-button").removeClass('active').addClass('nonactive');
}

function activateTab(tabSelector) {
    hideAllTabs();

    $("#" + tabSelector).show();
    $("#" + tabSelector + "-button").removeClass('nonactive').addClass('active');
	
	if (tabSelector == "screening") {
		// show buttons, hide reports
		$(".screening_report").hide();
		$(".report_switch").show();
	}
}

function showReport(report_name) {
	$(".report_switch").hide();
	$(".screening_report").hide();
	$("#" + report_name).show();
}

function logout() {
    $.ajax(APP_PATH_WEBROOT + "?logout=1");
//    window.location="https://passitonstudy.org";
}

$("document").ready(function() {
    // activateTab("allSitesData");
		activateTab("screening");
		showReport('screening_log');
	
	$('.sortable').tablesorter();
});