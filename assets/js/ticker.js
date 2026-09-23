(function () {
	"use strict";

	var PX_PER_SEC = 70;
	var MAX_CLONES = 24;

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	function imagesReady(root) {
		var imgs = root.querySelectorAll("img");
		var pending = [];

		for (var i = 0; i < imgs.length; i++) {
			if (!imgs[i].complete) {
				pending.push(
					new Promise(function (resolve) {
						this.addEventListener("load", resolve, { once: true });
						this.addEventListener("error", resolve, { once: true });
					}.bind(imgs[i]))
				);
			}
		}

		return Promise.all(pending);
	}

	function fill(wrapper) {
		var mover = wrapper.querySelector(".low-logo-ticker__mover");
		var group = mover && mover.querySelector(".low-logo-ticker__group");
		if (!mover || !group) {
			return;
		}

		if (prefersReducedMotion()) {
			return;
		}

		var target = Math.max(wrapper.clientWidth * 2, 1);
		var added = 0;

		while (mover.scrollWidth < target && added < MAX_CLONES) {
			var clone = group.cloneNode(true);
			clone.setAttribute("aria-hidden", "true");
			mover.appendChild(clone);
			added += 1;
		}

		var shift = group.getBoundingClientRect().width;
		if (shift < 1) {
			return;
		}

		var nextShift = shift + "px";
		if (wrapper.style.getPropertyValue("--low-logo-ticker-shift") !== nextShift) {
			wrapper.style.setProperty("--low-logo-ticker-shift", nextShift);
			wrapper.style.setProperty("--low-logo-ticker-duration", shift / PX_PER_SEC + "s");
		}
	}

	function init() {
		var wrappers = document.querySelectorAll(".low-logo-ticker__wrapper");

		for (var i = 0; i < wrappers.length; i++) {
			(function (wrapper) {
				imagesReady(wrapper).then(function () {
					fill(wrapper);

					if (typeof ResizeObserver === "undefined") {
						return;
					}

					var ro = new ResizeObserver(function () {
						fill(wrapper);
					});
					ro.observe(wrapper);
				});
			})(wrappers[i]);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
