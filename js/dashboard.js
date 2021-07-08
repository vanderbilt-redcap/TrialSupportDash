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

function updateEnrollmentChart(site_dag) {
	console.log('site_dag', site_dag);

	$.ajax({
		url: ENROLLMENT_CHART_DATA_AJAX_URL,
		type: "POST",
		data: {site_dag: site_dag},
		cache: false,
		dataType: "json"
	})
		.done(function(json) {
			console.log('received json: ', json);
			if (json.rows && json.rows.length > 1) {
				// update enrollment chart with new data
				json.rows.pop()
				var week_labels = [];
				var data1 = [];
				var data2 = [];
				json.rows.forEach(function(row) {
					week_labels.push(row[0]);
					data1.push(row[1]);
					data2.push(row[2]);
				})
				enrollment_chart.data.labels = week_labels;
				enrollment_chart.data.datasets[0].data = data1;
				enrollment_chart.data.datasets[1].data = data2;
				enrollment_chart.update();
			}

		});
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
	
	// when a user clicks on a site row in the enrollments table, select that row and update the chart
	$("body").on("mousedown touchstart", "#allSitesData tr:not('first_row')", function(clickEvent) {
		$("#allSitesData tr").removeClass('selected');
		var site_row = $(clickEvent.currentTarget);

		site_row.addClass('selected');
		// if empty, fetch total enrollments, otherwise get site specific enrollment data
		var site_dag = site_row.attr('data-dag') || "";

		updateEnrollmentChart(site_dag);
	});

	// when a user clicks on a site option in the Site Activation tab dropdown, select that site and update the Site Activation chart
	$("body").on("mousedown touchstart", "#activation .active-site-select .dropdown-item", function(clickEvent) {
		var site_name = $(clickEvent.target).text();
		var found_site_container;
		$('.activation-container').each(function(i, div) {
			if (site_name == $(div).find('h2').text()) {
				found_site_container = $(div);
			}
		});
		if (found_site_container.length) {
			$('.activation-container').hide();
			found_site_container.show();
		}
	});

	$("#links .links").hide();
	$("#links button.close-folder").hide();
	
	$('.sortable').tablesorter();

	// site activation table color coding
	$("tr.data td:not(.signoff):nth-child(n+3)").each(function(i, td) {
		var cell = $(td);

		var text_red = [
			"Initiated",
			"Awaiting Site Response"
		];
		var text_green = [
			"Complete",
			"Confirmed by VCC"
		];

		var digit_regex = /\d/;
		var text = cell.text();
		console.log('text', text);
		text = text.trim();
		if (digit_regex.test(text)) {
			// probably a date cell or count of days between site engaged/open for enrollment
			if (text != '') {
				cell.addClass('green');
			}
		} else {
			if (text_red.includes(text)) {
				cell.addClass('red');
			} else if (text_green.includes(text)) {
				cell.addClass('green');
			} else {
				cell.addClass('yellow');
			}
		}
	});

	// show first site in activation tab
	$('.activation-container').first().show();
});