(function ($) {
	"use strict";

	var $list = $("#low-logo-ticker-rows");
	var frame = null;
	var $activeRow = null;

	function reindexRows() {
		$list.children(".low-logo-ticker-row").each(function (i) {
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
							/low_logo_ticker_images\[[^\]]+\]/,
							"low_logo_ticker_images[" + i + "]"
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
		$row.find(".low-logo-ticker-row__attachment-id").val(attachment.id);

		var url = previewUrl(attachment);
		if (!url) {
			return;
		}

		var $preview = $row.find(".low-logo-ticker-row__preview");
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
			title: lowLogoTickerAdmin.chooseImage,
			button: { text: lowLogoTickerAdmin.useImage },
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

	$list.on("click", ".low-logo-ticker-row__choose", function (e) {
		e.preventDefault();
		if (typeof wp === "undefined" || !wp.media) {
			return;
		}
		$activeRow = $(this).closest(".low-logo-ticker-row");
		getFrame().open();
	});

	$list.on("click", ".low-logo-ticker-row__duplicate", function (e) {
		e.preventDefault();
		var $row = $(this).closest(".low-logo-ticker-row");
		$row.after($row.clone());
		reindexRows();
	});

	$list.on("click", ".low-logo-ticker-row__remove", function (e) {
		e.preventDefault();
		$(this).closest(".low-logo-ticker-row").remove();
		reindexRows();
	});

	$("#low-logo-ticker-add").on("click", function (e) {
		e.preventDefault();
		var template = $.trim($("#low-logo-ticker-row-template").html() || "");
		if (!template) {
			return;
		}
		var $row = $($.parseHTML(template)).filter(".low-logo-ticker-row");
		$list.append($row);
		reindexRows();
	});

	if ($.fn.sortable) {
		$list.sortable({
			handle: ".low-logo-ticker-row__handle",
			placeholder: "low-logo-ticker-row ui-sortable-placeholder",
			forcePlaceholderSize: true,
			update: reindexRows,
		});
	}
})(jQuery);
