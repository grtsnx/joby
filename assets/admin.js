jQuery(document).ready(function ($) {
	const $table = $("#ajs-countries-table tbody");
	const template = $("#ajs-row-template").html();

	// Add row
	$("#ajs-add-country").on("click", function () {
		$(".empty-row").remove();
		const index = $table.find("tr").length;
		const newRow = template.replace(/{{index}}/g, index);
		$table.append(newRow);
	});

	// Remove row
	$table.on("click", ".ajs-remove-row", function () {
		$(this).closest("tr").remove();
		if ($table.find("tr").length === 0) {
			$table.append('<tr class="empty-row"><td colspan="4">No countries added yet.</td></tr>');
		}

		// Re-index inputs
		reindexRows();
	});

	function reindexRows() {
		$table.find("tr").each(function (index) {
			$(this)
				.find("input")
				.each(function () {
					const name = $(this).attr("name");
					if (name) {
						const newName = name.replace(/ajs_countries\[\d+\]/, "ajs_countries[" + index + "]");
						$(this).attr("name", newName);
					}
				});
		});
	}

	// Trigger Sync
	$("#ajs-trigger-sync").on("click", function () {
		const $btn = $(this);
		$btn.prop("disabled", true).text("Starting Sync...");

		$.post(
			ajs_vars.ajax_url,
			{
				action: "ajs_trigger_sync",
				nonce: ajs_vars.nonce,
			},
			function (response) {
				if (response.success) {
					alert("Sync has been triggered and will run in the background.");
					location.reload();
				} else {
					alert("Error: " + response.data);
					$btn.prop("disabled", false).text("Start Manual Sync");
				}
			},
		);
	});
});
