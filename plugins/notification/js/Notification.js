/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global) {
	// "use strict";
	var PERMISSION_DEFAULT = "default";
	var PERMISSION_GRANTED = "granted";
	var PERMISSION_DENIED = "denied";
		// http://www.chromium.org/developers/design-documents/desktop-notifications/api-specification
	var PERMISSION = [
		PERMISSION_GRANTED, // PERMISSION_ALLOWED(0)
		PERMISSION_DEFAULT, // PERMISSION_NOT_ALLOWED(1)
		PERMISSION_DENIED   // PERMISSION_DENIED(2)
	];

	var defaults = {
		dir     : "auto",        // https://developer.mozilla.org/en-US/docs/Web/API/Notification.dir
		lang    : "",            // https://developer.mozilla.org/en-US/docs/Web/API/Notification.lang
		body    : "",            // https://developer.mozilla.org/en-US/docs/Web/API/Notification.body
		tag     : "",            // https://developer.mozilla.org/en-US/docs/Web/API/Notification.tag
		icon    : "",            // https://developer.mozilla.org/en-US/docs/Web/API/Notification.icon
		onclick : function () {}, // https://developer.mozilla.org/en-US/docs/Web/API/Notification.onclick
		onshow  : function () {}, // https://developer.mozilla.org/en-US/docs/Web/API/Notification.onshow
		onerror : function () {}, // https://developer.mozilla.org/en-US/docs/Web/API/Notification.onerror
		onclose : function () {}  // https://developer.mozilla.org/en-US/docs/Web/API/Notification.onclose
	};

	var nativeNotification;

	var isType = (function () {
		if (nativeNotification = global.Notification) {
			return 1;
		}
		else if (nativeNotification = global.webkitNotifications) {
			return 2;
		}
		else if (nativeNotification = navigator.mozNotification) {
			return 3;
		}

		nativeNotification = null;
		return 0;
	}());

	var isSupported = !!isType;

	function isString(value) {
		return (value && value.constructor === String);
	}
	function isObject(value) {
		return (value && value.constructor === Object);
	}
	var isArray = Array.isArray;
	var aps = Array.prototype.slice;

	function extend() {
		var args = aps.call(arguments),
			target = args[0];
		for (var i = 1; i < args.length; i++) {
			var arg = args[i];
			for (var key in arg) {
				if (arg.hasOwnProperty(key)) {
					target[key] = arg[key];
				}
			}
		}
		return target;
	}

	function config(params) {
		var opts = defaults;
		if (params && isObject(params)) {
			opts = extend({}, defaults, params);
		}
		return opts;
	}

	function addEvent(target, type, callback) {
		if (target.addEventListener) {
			target.addEventListener(type, callback);
		}
		// < IE 9
		else if (target.attachEvent && htmlEvents["on" + type]) {
			target.attachEvent("on" + type, callback);
		}
		else {
			target["on" + type] = callback;
		}
	}

	if (isSupported) {
		var Notification = global.Notification = function (title, options) {
			var notification;
			if (isString(title) && permissionLevel() === PERMISSION_GRANTED) {
				var settings = config(options);
				var events = ["click", "show", "error", "close"];
				switch (isType) {
					case 1:
						settings.icon = isObject(settings.icon) ? settings.icon.x32 : settings.icon;
						notification = new nativeNotification(title, settings);
						break;
					case 2:
						settings.ondisplay = settings.onshow;
						notification = nativeNotification.createNotification(settings.icon, title, settings.body);
						break;
					case 3:
						notification = nativeNotification.createNotification(title, settings.body, settings.icon);
						break;
				}
				addEvent(notification, "display", settings.onshow); // WebkitNotifications
				for (var i = 0; i < events.length; i++) {
					addEvent(notification, events[i], settings["on" + events[i]]);
				}
				if (!notification.close) {
					notification.close = function () {};
					if (notification.cancel) {
						notification.close = notification.cancel;
					}
				}
				if (notification.show) {
					notification.show();
				}
			}

			return notification;
		}

		var permissionLevel = global.Notification.permissionLevel = function () {
			var perm = PERMISSION_DEFAULT;
			if (nativeNotification.permissionLevel) {
				perm = nativeNotification.permissionLevel();
			}
			else if (nativeNotification.checkPermission) {
				perm = PERMISSION[nativeNotification.checkPermission()];
			}
			else if (nativeNotification.permission) {
				perm = nativeNotification.permission;
			}
			else if (isType === 3) {
				perm = PERMISSION_GRANTED;
			}

			return global.Notification.permission = perm;
		};

		var currentPermission = permissionLevel();

		var requestPermission = global.Notification.requestPermission = (function () {
			var reqPerm = nativeNotification.requestPermission;
			if (!reqPerm) {
				reqPerm = function (callback) {
					callback(permissionLevel());
				};
			}

			return reqPerm;
		})();
	}
})(this);

/* End of file */
