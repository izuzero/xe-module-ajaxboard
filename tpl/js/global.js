/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global) {
	"use strict";

	global.strip = function (input, allowed) {
		allowed = (
			((allowed || "") + "")
			.toLowerCase()
			.match(/<[a-z][a-z0-9]*>/g) || []
		).join("");

		var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi;
		var cmts_n_php_tags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;

		return input
			.replace(cmts_n_php_tags, "")
			.replace(tags, function (primary, secondary) {
				return (allowed.indexOf("<" + secondary.toLowerCase() + ">") > -1 ? primary : "");
			});
	};
})(this);

/* End of file */
