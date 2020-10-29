function hideAllTabs() {
    $("#allSitesData").hide()
    $("#allSitesData-button").removeClass('active').addClass('nonactive');
    $("#mySiteData").hide();
    $("#mySiteData-button").removeClass('active').addClass('nonactive');
    $("#communications").hide();
    $("#communications-button").removeClass('active').addClass('nonactive');
}

function activateTab(tabSelector) {
    hideAllTabs();

    $("#" + tabSelector).show();
    $("#" + tabSelector + "-button").removeClass('nonactive').addClass('active');
}

function logout() {
    $.ajax(APP_PATH_WEBROOT + "?logout=1");
//    window.location="https://passitonstudy.org";
}

$("document").ready(function() {
    activateTab("allSitesData");

	$('.sortable').tablesorter();
});