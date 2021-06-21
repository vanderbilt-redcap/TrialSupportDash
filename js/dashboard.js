function hideAllTabs() {
    $("#allSitesData").hide()
    $("#allSitesData-button").removeClass('active').addClass('nonactive');
    $("#mySiteData").hide();
    $("#mySiteData-button").removeClass('active').addClass('nonactive');
    $("#screening").hide();
    $("#screening-button").removeClass('active').addClass('nonactive');
    $("#links").hide();
    $("#links-button").removeClass('active').addClass('nonactive');
}

function activateTab(tabSelector) {
    hideAllTabs();

    $("#" + tabSelector).show();
    $("#" + tabSelector + "-button").removeClass('nonactive').addClass('active');
	
	if (tabSelector == "screening") {
		// show buttons, hide reports
		$(".screening_report").hide();
		$(".report_switch").show();
		$("span#report_title").text("");
		
	}

	if (tabSelector === "allSitesData") {
		$("#region").show();
	} else {
		$("#region").hide();
	}
}

function showReport(report_name) {
	// hide buttons and report divs
	$(".report_switch").hide();
	$(".screening_report").hide();
	
	// show report div
	$("#" + report_name).show();
	// set report title
	var titles = {screening_log: "Screening Log Report", exclusion: "Exclusion Report", screen_fail: "Screen Fail Report"};
	$("span#report_title").text(titles[report_name]);
}

function logout() {
    $.ajax(APP_PATH_WEBROOT + "?logout=1");
//    window.location="https://passitonstudy.org";
}

function copyToClipboard(element) {
	// see: https://stackoverflow.com/questions/22581345/click-button-copy-to-clipboard-using-jquery
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(element).text()).select();
    document.execCommand("copy");
    $temp.remove();
}

function clickedFolder(clickEvent) {
	var folder = $(clickEvent.currentTarget);
	var folder_index = folder.attr("data-index") - 1;
	
	$("#links .folders").hide();
	
	// unhide links div
	$("#links div.links").show();
	$("#links button.close-folder").show();
	$("#links div.links .card").each(function(i, link_element) {
		var this_link = $(link_element);
		if (this_link.attr("data-folder-index") == folder_index) {
			this_link.show();
		} else {
			this_link.hide();
		}
	})
}

function getSelectedValue() {
	//get dropdown id update on when there is a change
	$('#region').change(function () {
		//set value of selectbox
		var filterValue = $(this).val();

		

		//loop through to get each data attribute that matches region 
		$('#allSitesData .region').each(function (index, value) {
			var region = $(this).attr('data-region');
			//hiding table row .region area
			$(this).hide();

			if (isEmpty(filterValue)) {
				$(this).show()
			}
			//if match from dropdown and json in config show results
			if (region === filterValue) {
				$(this).show();
			}
			//hide class if there is data
			$('.no-sites').hide();

			

		});
		//if there is no match display string statement 
		if (!$('.region').is(':visible')) {
			$('tbody:first').append('<tr class="no-sites"><td colspan="5">Sorry, no sites</td></tr>');
		} 
	})
}

$("document").ready(function () {

	getSelectedValue();
	
    activateTab(startTab);
		
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
			if (json.rows && json.rows.length > 1) {
				// replace table rows with new data
				$("#screening_log div table tbody").empty();
				json.rows.forEach(function(row) {
					var tablerow = "<tr><td>" + row[0] + "</td><td>" + row[1] + "</td><td>" + row[2] + "</td></tr>";
					$("#screening_log div table tbody").append(tablerow);
				})
				
				// update chart with new data (after removing last line)
				json.rows.pop()
				var week_labels = [];
				var data1 = [];
				var data2 = [];
				json.rows.forEach(function(row) {
					week_labels.push(row[0]);
					data1.push(row[1]);
					data2.push(row[2]);
				})
				screening_log_chart.data.labels = week_labels;
				screening_log_chart.data.datasets[0].data = data1;
				screening_log_chart.data.datasets[1].data = data2;
				screening_log_chart.update();
			}
			
		});
	})
	
	// make copy to clipboard buttons work
	$("body").on("mousedown touchstart", "button.clipboard", function() {
		var url_span = $(this).closest("div.card").find('a.link_url')
		copyToClipboard(url_span);
	});
	
	// update links shown when user clicks helpful links folder
	$("body").on("mousedown touchstart", "#links div.folder", clickedFolder);
	
	$("body").on("mousedown touchstart", "#links .close-folder", function() {
		$("#links .folders").show();
		$("#links .links").hide();
		$("#links button.close-folder").hide();
	});
	
	$("#links .links").hide();
	$("#links button.close-folder").hide();
	
	$('.sortable').tablesorter();
});