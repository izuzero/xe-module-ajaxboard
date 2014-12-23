/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";

	var aps = Array.prototype.slice;

	function con() {
		var self = this;
		var con = global.console;
		var level = ["debug", "log", "info", "warn", "error"];

		this.stack = [];
		$.each(level, function(key, val) {
			self[val] = function() {
				var args = aps.call(arguments);
				(con && $.isFunction(con[val])) ?
					con[val].apply(con, args) :
					this.stack.push([val].concat(args));
			};
		});
	}
	global.console = new con();

	function parseUrl(str) {
		var i = 14;
		var uri = {};
		var reg = /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/;
		var key = ["source", "scheme", "authority", "userInfo", "user", "pass", "host", "port", "relative", "path", "directory", "file", "query", "fragment"];
		var exec = reg.exec(str);

		while (i--) {
			if (exec[i]) {
				uri[key[i]] = exec[i];
			}
		}

		delete uri.source;
		return uri;
	}

	function unparseUrl(args) {
		return (
			(args.scheme || "http") + "://" +
			(args.user || "") +
			(args.user || args.pass ? (args.pass || "") + "@" : "") +
			(args.host || "") +
			(args.port ? ":" + args.port : "") +
			(args.path || "") +
			(args.query ? "?" + args.query : "") +
			(args.fragment ? "#" + args.fragment : "")
		);
	}

	function buildQuery(args, numeric_prefix, separator) {
		var tmp = [];
		function urlencode(str) {
			str = (str + "").toString();
			return encodeURIComponent(str)
				.replace(/!/g, "%21")
				.replace(/'/g, "%27")
				.replace(/\(/g, "%28")
				.replace(/\)/g, "%29")
				.replace(/\*/g, "%2A")
				.replace(/%20/g, "+");
		}
		function buildQueryHelper(key, val, separator) {
			var tmp = [];
			if (val === true) {
				val = "1";
			}
			else if (val === false) {
				val = "0";
			}
			if (val != null) {
				if ($.isPlainObject(val)) {
					$.each(val, function (k, v) {
						tmp.push(buildQueryHelper(key + "[" + k + "]", v, separator));
					});
					return tmp.join(separator);
				}
				else if (!$.isFunction(val)) {
					return urlencode(key) + "=" + urlencode(val);
				}
			}
			else {
				return "";
			}
		}
		if (!separator) {
			separator = "&";
		}
		$.each(args, function (key, val) {
			if (numeric_prefix && $.isNumeric(key)) {
				key = String(numeric_prefix) + key;
			}
			var query = buildQueryHelper(key, val, separator);
			if (query !== "") {
				tmp.push(query);
			}
		});

		return tmp.join(separator);
	}

	function extractQuery(str) {
		var obj = {};
		var cast = {"null": null, "true": true, "false": false};

		str = String(str).trim()
			.replace(/^.*[?]|#.*$/g, "")
			.replace(/\+/g, " ")
			.split("&");

		while (str.length) {
			var pair = str.shift().split("="),
				key = decodeURIComponent(pair[0]);
			if (pair.length == 2) {
				var val = decodeURIComponent(pair[1]);
				if (val && !isNaN(val)) {
					val = Number(val);
				}
				else if (val.toLowerCase() === "undefined") {
					val = undefined;
				}
				else if (cast[val.toLowerCase()] !== undefined) {
					val = cast[val.toLowerCase()];
				}
				if ($.isArray(obj[key])) {
					obj[key].push(val);
				}
				else if (obj[key] !== undefined) {
					obj[key] = [obj[key], val];
				}
				else {
					obj[key] = val;
				}
			}
			else if (key) {
				obj[key] = undefined;
			}
		}

		return obj;
	}

	function ajaxboardStorage() {
		this.connect = false;
		this.triggers = {};
		this.triggers.before = [];
		this.triggers.after = [];
	}
	ajaxboardStorage.prototype = {
		insertTrigger: function (scope, name, hook, callback) {
			var stack = this.triggers[hook];
			if ($.isArray(stack)) {
				stack.push({scope: scope, name: name, callback: callback});
				return true;
			}

			return true;
		},
		getTriggers: function (name, hook)
		{
			var triggers = [];
			var stack = this.triggers[hook];
			if (!$.isArray(stack)) {
				return triggers;
			}

			stack.sort(function (primary, secondary) {
				var idx;
				(primary.name == secondary.name) ? idx = 0 :
				(secondary.name == name) ? idx = 1 : idx = -1;
				return idx;
			});
			$.each(stack, function (key, trigger) {
				var exception = false;
				(trigger.name == name) ?
					triggers.push(trigger) :
					exception = true;
				return !exception;
			});

			return triggers;
		},
		triggerCall: function () {
			var args = aps.call(arguments);
			var name = args.shift();
			var hook = args.shift();
			var triggers = this.getTriggers(name, hook);
			var exception = false;

			$.each(triggers, function (key, trigger) {
				var scope = trigger.scope;
				var callback = trigger.callback;
				if ($.isFunction(callback)) {
					exception = !callback.apply(scope, args || []);
				}
				return !exception;
			});

			return !exception;
		}
	};
	var storage
		= global.ajaxboardStorage
		= new ajaxboardStorage();

	$(function () {
		function ajaxboard(request_uri, current_url, current_mid) {
			this.request_uri = request_uri;
			this.current_url = current_url;
			this.current_mid = current_mid;

			var self = this;
			var extension = ["insertTrigger", "getTriggers", "triggerCall"];
			$.each(extension, function (key, val) {
				self[val] = function () {
					return storage[val].apply(storage, aps.call(arguments));
				};
			});
		}
		ajaxboard.prototype = {
			ajax: function (data_type, request_url, module, act, params) {
				params = $.extend(extractQuery(request_url), params || {});
				params.module = module || params.module;
				params.act = act || params.act;
				if (global.xeVid) {
					params.vid = global.xeVid;
				}

				var isSSL = (function(act) {
					return !!(
						global.enforce_ssl === true ||
							(act &&
							global.ssl_actions &&
							$.isArray(global.ssl_actions) &&
							$.inArray(act, global.ssl_actions) > -1)
					);
				})(params.act);

				request_url = parseUrl(request_url);
				delete request_url.query;
				delete request_url.fragment;
				if (isSSL) {
					request_url.scheme = "https";
					request_url.port = global.https_port;
				}
				request_url = unparseUrl(request_url);

				var data, submit_type, content_type;
				switch (data_type = data_type.toLowerCase()) {
					case "xml":
						var xml = [];
						xml.push('<?xml version="1.0" encoding="UTF-8"?>');
						xml.push("<methodCall>");
						xml.push("<params>");
						$.each(params, function(key, val) {
							xml.push("<" + key + "><![CDATA[" + val + "]]></" + key + ">");
						});
						xml.push("</params>");
						xml.push("</methodCall>");

						data = xml.join("\n");
						submit_type = "POST";
						content_type = "application/xml";
						break;

					case "json":
					case "jsonp":
						data = $.param(params);
						submit_type = "POST";
						content_type = "application/json";
						break;

					case "html":
						data = $.param(params);
						submit_type = "GET";
						content_type = "text/html";
						break;

					default:
						throw new TypeError("Invalid data type");
				}

				var $wfsr = $(".wfsr");
				if ($wfsr.length && global.show_waiting_message) {
					var timeout_id = $wfsr.data("timeout_id");
					if (timeout_id) {
						clearTimeout(timeout_id);
					}
					$wfsr
						.css("opacity", 0)
						.data("timeout_id", setTimeout(function() {$(".wfsr").css("opacity", "")}, 1000))
						.html(global.waiting_message)
						.show();
				}

				function onsuccess() {
					$(".wfsr").hide().trigger("cancel_confirm");
				}
				function onerror() {
					$(".wfsr").css("display", "none");
				}

				var opts = {};
				opts.url         = request_url;
				opts.type        = submit_type;
				opts.dataType    = data_type;
				opts.contentType = content_type;
				opts.data        = data;
				opts.success     = onsuccess;
				opts.error       = onerror;
				opts.timeout     = this.timeout;
				opts.global      = false;

				var ajax = $.ajax(opts);
					ajax.params = params;

				return ajax;
			},
			connect: function (type) {
				var self = this;
				handler.getConfig().done(function (response, status, xhr) {
					delete response.message_type;
					delete response.message;
					delete response.error;
					$.extend(global.xe.lang, response.lang);
					$.extend(self, response);

					var idx = Number(type || self.type);
					if (!(global.io && $.isFunction(global.io))) {
						idx = 2;
					}
					if (self.server) {
						self.server.close();
					}
					if (!self.triggerCall("events.connect", "before", type)) {
						return;
					}
					switch (idx) {
						// WEBSOCKET
						case 1:
							// Host
							var host, uri_info;
							(uri_info = parseUrl(self.server_host)).host ||
							(uri_info = parseUrl(self.request_uri));
							uri_info.port = self.server_port;
							host = unparseUrl(uri_info);

							// Options
							var options = {};
							options.query = buildQuery({member_srl: self.member_srl});
							options.timeout = self.timeout;
							options.reconnection = false;

							// Server object
							var server = self.server = new global.io(host, options);

							// Bind events
							server.on("connect", function () {
								server.on("broadcastMessage", function (obj) {
									self.triggerCall("events.broadcastMessage", "before", obj);
								});
								server.on("insertDocument", function (obj) {
									self.triggerCall("events.insertDocument", "before", obj);
									if (self.getTriggers("events.insertDocument.detail", "before")) {
										handler.getDocument(obj.target_srl).done(function (response, status, xhr) {
											self.triggerCall("events.insertDocument.detail", "before", obj, response);
										});
									}
								});
								server.on("deleteDocument", function (obj) {
									self.triggerCall("events.deleteDocument", "before", obj);
								});
								server.on("voteDocument", function (obj) {
									self.triggerCall("events.voteDocument", "before", obj);
								});
								server.on("insertComment", function (obj) {
									self.triggerCall("events.insertComment", "before", obj);
									if (self.getTriggers("events.insertComment.detail", "before")) {
										handler.getComment(obj.target_srl).done(function (response, status, xhr) {
											self.triggerCall("events.insertComment.detail", "before", obj, response);
										});
									}
								});
								server.on("deleteComment", function (obj) {
									self.triggerCall("events.deleteComment", "before", obj);
								});
								server.on("voteComment", function (obj) {
									self.triggerCall("events.voteComment", "before", obj);
								});
								self.triggerCall("events.connect", "after", type);
							});
							server.on("disconnect", function (reason) {
								if (self.triggerCall("events.error", "before", reason)) {
									console.error("socket.io disconnected:", reason);
									self.connect(1);
								}
							});
							server.on("connect_error", function (reason) {
								if (self.triggerCall("events.error", "before", reason)) {
									console.error("socket.io error:", reason);
									self.connect(2);
								}
							});
							server.on("connect_timeout", function () {
								if (self.triggerCall("events.timeout", "before")) {
									console.error("socket.io timeout.");
									self.connect(1);
								}
							});
							break;

						// SERVER-SENT-EVENT
						case 2:
						default:
							var uid = self.getUid();
							handler.destroyUid(uid).done(function () {
								// Host
								var host = self.request_uri
									.setQuery("module", "ajaxboard")
									.setQuery("act", "getAjaxboardListener")
									.setQuery("uid", uid);

								// Server object
								var server = self.server = new EventSource(host);

								// Bind events
								server.addEventListener("broadcastMessage", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.broadcastMessage", "before", obj);
								}, false);
								server.addEventListener("insertDocument", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.insertDocument", "before", obj);
									if (self.getTriggers("events.insertDocument.detail", "before")) {
										handler.getDocument(obj.target_srl).done(function (response, status, xhr) {
											self.triggerCall("events.insertDocument.detail", "before", obj, response);
										});
									}
								}, false);
								server.addEventListener("deleteDocument", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.deleteDocument", "before", obj);
								}, false);
								server.addEventListener("voteDocument", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.voteDocument", "before", obj);
								}, false);
								server.addEventListener("insertComment", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.insertComment", "before", obj);
									if (self.getTriggers("events.insertComment.detail", "before")) {
										handler.getComment(obj.target_srl).done(function (response, status, xhr) {
											self.triggerCall("events.insertComment.detail", "before", obj, response);
										});
									}
								}, false);
								server.addEventListener("deleteComment", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.deleteComment", "before", obj);
								}, false);
								server.addEventListener("voteComment", function (e) {
									var obj = $.parseJSON(e.data);
									self.triggerCall("events.voteComment", "before", obj);
								}, false);
								$(window).on("beforeunload", function (e) {
									handler.destroyUid(uid);
								});
								self.triggerCall("events.connect", "after", type);
							});
					}
				});

				return this;
			},
			getUid: function () {
				return this.uid || this.generateUid();
			},
			generateUid: function () {
				var addPadding = function (val, len) {
					var zeros = "0";
					for (var i = 2; i < len; i++) {
						zeros += "0";
					}
					return (zeros + String(val)).slice(-len);
				};

				var date = new Date();
				var stack = [];
				var dateStack = [];
				stack.push(addPadding(this.member_srl || 0, 11));
				stack.push(addPadding(Math.ceil(Math.random() * 0x80000000), 11));
				dateStack.push(String(date.getFullYear()));
				dateStack.push(addPadding(date.getMonth() + 1, 2));
				dateStack.push(addPadding(date.getDate(), 2));
				dateStack.push(addPadding(date.getHours(), 2));
				dateStack.push(addPadding(date.getMinutes(), 2));
				dateStack.push(addPadding(date.getSeconds(), 2));
				stack.push(dateStack.join(""));

				return this.uid = stack.join("-");
			},
			scrolling: function (type, time, selector, wrapper) {
				var $window = $(window);
				var $selector = $(selector);
				var $wrapper = $(wrapper || "html, body");
				if (!($selector.length && $wrapper.length)) {
					return this;
				}

				var pos = $selector.offset().top;
					pos -= $wrapper.offset().top;
					pos += $wrapper.scrollTop();
				switch (type) {
					case 1:
						break;
					case 2:
						pos -= $window.height() / 2;
						pos += $selector.outerHeight(true) / 2;
						break;
					case 3:
						pos += $selector.outerHeight(true);
						break;
					default:
						console.error("Unknown scrolling type");
						return this;
				}
				$wrapper.stop().animate({scrollTop: pos}, time, "easeInOutExpo");

				return this;
			},
			clearEditor: function () {
				// Default
				$("input[name='comment_srl']").val("");
				// XpressEditor
				if ($(".xpress-editor").length) {
					var seq = $(".xpress_xeditor_editing_area_container").attr("id").split("-")[3];
					var uploadFileObj = $("#uploaded_file_list_" + seq + " option");
					var uploadPreviewObj = $("#preview_uploaded_" + seq);

					$("#editor_iframe_" + seq).contents().find("body").html("");
					if (uploadFileObj.length) {
						uploadedFiles = [];
						uploadFileObj.remove();
						uploadPreviewObj.empty();
						uploaderSettings[seq].uploadTargetSrl = "";
					}
				}
				// XE TextEditor
				var $xete = $(".xeTextEditor");
				if ($xete.length) {
					$xete.find("textarea").val("");
				}
				// TextyleEditor
				$(".textyleEditor .del").trigger("click");

				this.triggerCall("clearEditor", "after");

				return this;
			},
			deleteDocument: function (document_srl, redirect_url, callback, fallback) {
				var self = this;
				handler.getDocument(document_srl).done(function (response, status, xhr) {
					if (response.is_granted) {
						if (confirm(self.lang.msg_ajaxboard_delete_document)) {
							handler.deleteDocument(document_srl).done(function (response, status, xhr) {
								if ($.isFunction(callback)) {
									callback(response, status, xhr);
								}
							});
						}
					}
					else if (response.is_exists) {
						if (confirm(self.lang.msg_ajaxboard_password_required)) {
							location.href = redirect_url;
						}
					}
					else if ($.isFunction(fallback)) {
						fallback(document_srl);
					}
				});

				return this;
			},
			deleteComment: function (comment_srl, redirect_url, callback, fallback) {
				var self = this;
				handler.getComment(comment_srl).done(function (response, status, xhr) {
					if (response.is_granted) {
						if (confirm(self.lang.msg_ajaxboard_delete_comment)) {
							handler.deleteComment(comment_srl).done(function (response, status, xhr) {
								if ($.isFunction(callback)) {
									callback(response, status, xhr);
								}
							});
						}
					}
					else if (response.is_exists) {
						if (confirm(self.lang.msg_ajaxboard_password_required)) {
							location.href = redirect_url;
						}
					}
					else if ($.isFunction(fallback)) {
						fallback(comment_srl);
					}
				});

				return this;
			}
		};
		var core
			= global.ajaxboard
			= new ajaxboard(global.request_uri, global.current_url, global.current_mid);

		function ajaxboardHandler(parent) {
			this.oApp = parent;
		}
		ajaxboardHandler.prototype = {
			getConfig: function () {
				return this.oApp.ajax(
					"json",
					this.oApp.current_url,
					"ajaxboard",
					"getAjaxboardConfig"
				);
			},
			destroyUid: function (uid) {
				return this.oApp.ajax(
					"json",
					this.oApp.current_url,
					"ajaxboard",
					"procAjaxboardDestroyUid",
					{uid: uid}
				);
			},
			getDocument: function (document_srl) {
				return this.oApp.ajax(
					"json",
					this.oApp.current_url,
					"ajaxboard",
					"getAjaxboardDocument",
					{document_srl: document_srl}
				);
			},
			deleteDocument: function (document_srl) {
				return this.oApp.ajax(
					"json",
					this.oApp.request_uri,
					"board",
					"procBoardDeleteDocument",
					{document_srl: document_srl}
				);
			},
			getComment: function (comment_srl) {
				return this.oApp.ajax(
					"json",
					this.oApp.current_url,
					"ajaxboard",
					"getAjaxboardComment",
					{comment_srl: comment_srl}
				);
			},
			deleteComment: function (comment_srl) {
				return this.oApp.ajax(
					"json",
					this.oApp.request_uri,
					"board",
					"procBoardDeleteComment",
					{comment_srl: comment_srl}
				);
			}
		};
		var handler = new ajaxboardHandler(core);

		if (storage.connect === true) {
			core.connect();
		}
	});
})(this, jQuery);

/* End of file */
