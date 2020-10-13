function hideAllTabs() {
    $("#allSitesData").hide();
    $("#mySiteData").hide();
    $("#communications").hide();
}

$("document").ready(function() {
    hideAllTabs();
    $("#allSitesData").show();
});