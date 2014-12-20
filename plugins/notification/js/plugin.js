/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	global.ajaxboardStorage.connect = true;
	global.allowNotification = function () {
		Notification.requestPermission(function (permission) {
			$("#permission-description").fadeOut();
		});
	}
	global.denyNotification = function () {
		var date = new Date();
			date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
		setCookie("notification_denied", "Y", date);
		$("#permission-description").fadeOut();
	};
	$(function () {
		function Plugin() {
			this.oApp = global.ajaxboard;
			var self = this;
			var triggers = [
				[ "events.broadcastMessage",      "before", "triggerBroadcastMessage" ],
				[ "events.insertDocument.detail", "before", "triggerInsertDocument"   ],
				[ "events.insertComment.detail",  "before", "triggerInsertComment"    ]
			];
			$.each(triggers, function (key, trigger) {
				self.oApp.insertTrigger(self, trigger[0], trigger[1], self[trigger[2]]);
			});
			if (Notification.permission == "default" && getCookie("notification_denied") != "Y") {
				$("#permission-description").fadeIn();
			}
		}
		Plugin.prototype = {
			triggerBroadcastMessage: function (obj) {
				var onshow = function (e) {
					setTimeout(function () {
						e.currentTarget.close();
					}, 6000);
				}
				var opts = {};
				opts.body = this.strip(obj.extra_vars.message);
				opts.tag = NOTIFICATION_ID + ":broadcastMessage:" + NOTIFICATION.length;
				opts.icon = NOTIFICATION_ICON;
				opts.onshow = onshow;
				NOTIFICATION.push(new Notification(NOTIFICATION_BRO_TITLE, opts));
			},
			triggerInsertDocument: function (obj, oDocument) {
				var redirect = this.oApp.current_url
					.setQuery("document_srl", obj.target_srl);
				var onclick = function (e) {
					global.focus();
					location.href = redirect;
				}
				var onshow = function (e) {
					setTimeout(function () {
						e.currentTarget.close();
					}, 2000);
				}
				if ($.inArray(Number(obj.module_srl), NOTIFICATION_USER_INFO) > -1) {
					var opts = {};
					opts.body = this.strip(oDocument.title);
					opts.tag = NOTIFICATION_ID + "insertDocument:" + obj.target_srl;
					opts.icon = NOTIFICATION_ICON;
					opts.onclick = onclick;
					opts.onshow = onshow;
					NOTIFICATION.push(new Notification(NOTIFICATION_DOC_TITLE + " #" + oDocument.browser_title, opts));
				}
			},
			triggerInsertComment: function (obj, oDocument) {
				var redirect = this.oApp.current_url
					.setQuery("module", "ajaxboard")
					.setQuery("act", "procAjaxboardRedirect")
					.setQuery("type", "comment")
					.setQuery("comment_srl", obj.target_srl);
				var onclick = function (e) {
					global.focus();
					location.href = redirect;
				}
				var onshow = function (e) {
					setTimeout(function () {
						e.currentTarget.close();
					}, 6000);
				}
				if (this.oApp.member_srl &&
					this.oApp.member_srl != obj.target_member_srl &&
					this.oApp.member_srl == obj.extra_vars.parent_member_srl) {
					var opts = {};
					opts.body = this.strip(oDocument.content);
					opts.tag = NOTIFICATION_ID + ":insertComment:" + obj.target_srl;
					opts.icon = NOTIFICATION_ICON;
					opts.onclick = onclick;
					opts.onshow = onshow;
					NOTIFICATION.push(new Notification(NOTIFICATION_COM_TITLE + " #" + oDocument.browser_title, opts));
				}
			},
			strip: function (input, allowed) {
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
			}
		};
		if (typeof Notification !== "undefined") {
			var register = new Plugin();
		}
	});
})(this, jQuery);

/* End of file */
