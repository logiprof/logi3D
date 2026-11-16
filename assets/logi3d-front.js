/* Logi3D : hauteur automatique des animations (la largeur suit le contenu de la page).
   Stable dans tous les navigateurs : pas de barre de défilement dans l'animation,
   mesures regroupées, et aucun redimensionnement pendant le plein écran. */
(function () {
	"use strict";

	function isFs() {
		return !!(document.fullscreenElement || document.webkitFullscreenElement);
	}

	function measure(f) {
		if (isFs()) return;
		try {
			var d = f.contentDocument;
			if (!d || !d.body || d.URL === "about:blank") return;
			if (!d.documentElement.hasAttribute("data-logi3d-embed")) {
				d.documentElement.setAttribute("data-logi3d-embed", "1");
				f.setAttribute("scrolling", "no");
				d.documentElement.style.overflow = "hidden";
				d.body.style.overflow = "hidden";
			}
			var h = Math.ceil(d.body.getBoundingClientRect().height);
			if (h > 50 && Math.abs(h - f.offsetHeight) > 2) f.style.height = h + "px";
		} catch (e) { /* autre domaine : on garde la hauteur du code court */ }
	}

	function setup(w) {
		if (w.getAttribute("data-logi3d-ready")) return;
		w.setAttribute("data-logi3d-ready", "1");
		var f = w.querySelector("iframe");
		if (!f) return;
		var pending = false;
		function schedule() {
			if (pending) return;
			pending = true;
			requestAnimationFrame(function () { pending = false; measure(f); });
		}
		function watch() {
			schedule();
			try {
				var d = f.contentDocument;
				if (window.ResizeObserver && d && d.body) new ResizeObserver(schedule).observe(d.body);
			} catch (e) {}
			setTimeout(schedule, 600);
			setTimeout(schedule, 2000);
		}
		f.addEventListener("load", watch);
		window.addEventListener("resize", schedule);
		document.addEventListener("fullscreenchange", function () { setTimeout(schedule, 150); });
		document.addEventListener("webkitfullscreenchange", function () { setTimeout(schedule, 150); });
		try { if (f.contentDocument && f.contentDocument.readyState === "complete") watch(); } catch (e) {}
	}

	function init() {
		var list = document.querySelectorAll(".logi3d-anim");
		for (var i = 0; i < list.length; i++) setup(list[i]);
	}
	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
	else init();
})();
