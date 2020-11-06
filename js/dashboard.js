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
	
	// get new Screening Log Report when select#site changes
	$("select#site").change('change', function() {
		var selected_site = "";
		$(this).find("option:selected").each(function() {
			selected_site += $( this ).text() + " ";
		});
		
		$.ajax({
			url: SCREENING_LOG_DATA_AJAX_URL,
			type: "POST",
			data: {site_name: selected_site},
			cache: false,
			dataType: "json"
		})
		.done(function(json) {
			console.log('data pull response', json)
			if (json.rows && json.rows.length > 1) {
				// replace table rows with new data
				$("#screening_log div table tbody").empty();
				json.rows.forEach(function(row) {
					var tablerow = "<tr><td>" + row[0] + "</td><td>" + row[1] + "</td><td>" + row[2] + "</td></tr>";
					$("#screening_log div table tbody").append(tablerow);
				})
			}
			
		});
	})
	
	$('.sortable').tablesorter();
});