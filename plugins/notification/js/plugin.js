/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	if (typeof global.Notification === "undefined") {
		return;
	}
	global.allowNotification = function () {
		Notification.requestPermission(function (permission) {
			$("#permission-description").fadeOut();
		});
	};
	global.denyNotification = function () {
		var date = new Date();
			date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
		setCookie("notification_denied", "Y", date);
		$("#permission-description").fadeOut();
	};
	function strip(input, allowed) {
		allowed = (
			((allowed || "") + "")
			.toLowerCase()
			.match(/<[a-z][a-z0-9]*>/g) || []
		).join("");

		return input
			.replace(/<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi, "")
			.replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, function (primary, secondary) {
				return (allowed.indexOf("<" + secondary.toLowerCase() + ">") > -1 ? primary : "");
			});
	}
	$(function () {
		var core = global.ajaxboard;
		core.insertTrigger("events.sendMessage", "before", function (obj) {
			var opts = {
				body: strip(obj.extra_vars.title),
				icon: NOTIFICATION_ICON,
				tag: NOTIFICATION_ID + ":sendMessage:" + NOTIFICATION.length,
				onclick: function (e) {
					global.focus();
					location.href = core.current_url
						.setQuery("act", "dispCommunicationMessages");
				},
				onshow: function (e) {
					setTimeout(function () {
						e.currentTarget.close();
					}, NOTIFICATION_MES_DURATION);
				}
			};
			NOTIFICATION.push(new Notification(NOTIFICATION_MES_TITLE, opts));
		});
		core.insertTrigger("events.broadcastMessage", "before", function (obj) {
			var opts = {
				body: strip(obj.extra_vars.message),
				icon: NOTIFICATION_ICON,
				tag: NOTIFICATION_ID + ":broadcastMessage:" + NOTIFICATION.length,
				onshow: function (e) {
					setTimeout(function () {
						e.currentTarget.close();
					}, NOTIFICATION_BRO_DURATION);
				}
			};
			NOTIFICATION.push(new Notification(NOTIFICATION_BRO_TITLE, opts));
		});
		if (NOTIFICATION_USER_INFO) {
			core.insertTrigger("events.insertDocument.detail", "before", function (obj, oDocument) {
				if ($.inArray(Number(obj.module_srl), NOTIFICATION_USER_INFO) < 0) {
					return;
				}
				var opts = {
					body: strip(oDocument.title),
					icon: NOTIFICATION_ICON,
					tag: NOTIFICATION_ID + "insertDocument:" + obj.target_srl,
					onclick: function (e) {
						global.focus();
						location.href = core.request_uri
							.setQuery("document_srl", obj.target_srl);
					},
					onshow: function (e) {
						setTimeout(function () {
							e.currentTarget.close();
						}, NOTIFICATION_DOC_DURATION);
					}
				};
				NOTIFICATION.push(new Notification(NOTIFICATION_DOC_TITLE + " #" + oDocument.browser_title, opts));
			});
		}
		if (core.member_srl) {
			core.insertTrigger("events.insertComment.detail", "before", function (obj, oComment) {
				if (!(core.member_srl != obj.target_member_srl &&
					core.member_srl == obj.parent_member_srl)) {
					return;
				}
				var opts = {
					body: strip(oComment.content),
					icon: NOTIFICATION_ICON,
					tag: NOTIFICATION_ID + ":insertComment:" + obj.target_srl,
					onclick: function (e) {
						global.focus();
						location.href = core.request_uri
							.setQuery("act", "procAjaxboardRedirect")
							.setQuery("type", "C")
							.setQuery("target_srl", obj.target_srl);
					},
					onshow: function (e) {
						setTimeout(function () {
							e.currentTarget.close();
						}, NOTIFICATION_COM_DURATION);
					}
				};
				NOTIFICATION.push(new Notification(NOTIFICATION_COM_TITLE + " #" + oComment.browser_title, opts));
			});
		}
		if (Notification.permission == "default" && getCookie("notification_denied") != "Y") {
			$("#permission-description").fadeIn();
		}
	});
})(this, jQuery);

/* End of file */
