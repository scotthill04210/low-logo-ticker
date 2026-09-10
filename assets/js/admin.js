(function ($) {
	"use strict";

	var $list = $("#sepa-logo-ticker-rows");
	var frame = null;
	var $activeRow = null;

	function reindexRows() {
		$list.children(".sepa-logo-ticker-row").each(function (i) {
			$(this)
				.find("[name]")
				.each(function () {
					var name = $(this).attr("name");
					if (!name) {
						return;
					}
					$(this).attr(
						"name",
						name.replace(
							/sepa_logo_ticker_images\[[^\]]+\]/,
							"sepa_logo_ticker_images[" + i + "]"
						)
					);
				});
		});
	}

	function previewUrl(attachment) {
		if (attachment.sizes) {
			if (attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
				return attachment.sizes.thumbnail.url;
			}
			if (attachment.sizes.medium && attachment.sizes.medium.url) {
				return attachment.sizes.medium.url;
			}
		}
		return attachment.url || "";
	}

	function setRowImage($row, attachment) {
		$row.find(".sepa-logo-ticker-row__attachment-id").val(attachment.id);

		var url = previewUrl(attachment);
		if (!url) {
			return;
		}

		var $preview = $row.find(".sepa-logo-ticker-row__preview");
		var $img = $preview.children("img");
		if (!$img.length) {
			$img = $("<img />", { alt: "" }).appendTo($preview);
		}
		$img.attr("src", url);
	}

	function getFrame() {
		if (frame) {
			return frame;
		}

		frame = wp.media({
			title: sepaLogoTickerAdmin.chooseImage,
			button: { text: sepaLogoTickerAdmin.useImage },
			library: { type: "image" },
			multiple: false,
		});

		frame.on("select", function () {
			var selection = frame.state().get("selection");
			if (!selection || !selection.first()) {
				return;
			}
			if (!$activeRow || !$activeRow.length) {
				return;
			}
			setRowImage($activeRow, selection.first().toJSON());
		});

		return frame;
	}

	$list.on("click", ".sepa-logo-ticker-row__choose", function (e) {
		e.preventDefault();
		if (typeof wp === "undefined" || !wp.media) {
			return;
		}
		$activeRow = $(this).closest(".sepa-logo-ticker-row");
		getFrame().open();
	});

	$list.on("click", ".sepa-logo-ticker-row__duplicate", function (e) {
		e.preventDefault();
		var $row = $(this).closest(".sepa-logo-ticker-row");
		$row.after($row.clone());
		reindexRows();
	});

	$list.on("click", ".sepa-logo-ticker-row__remove", function (e) {
		e.preventDefault();
		$(this).closest(".sepa-logo-ticker-row").remove();
		reindexRows();
	});

	$("#sepa-logo-ticker-add").on("click", function (e) {
		e.preventDefault();
		var template = $.trim($("#sepa-logo-ticker-row-template").html() || "");
		if (!template) {
			return;
		}
		var $row = $($.parseHTML(template)).filter(".sepa-logo-ticker-row");
		$list.append($row);
		reindexRows();
	});

	if ($.fn.sortable) {
		$list.sortable({
			handle: ".sepa-logo-ticker-row__handle",
			placeholder: "sepa-logo-ticker-row ui-sortable-placeholder",
			forcePlaceholderSize: true,
			update: reindexRows,
		});
	}
})(jQuery);
