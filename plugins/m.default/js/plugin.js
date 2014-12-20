/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	global.ajaxboardStorage.connect = true;
	$(function () {
		function Plugin() {
			this.oApp = global.ajaxboard;
			var self = this;
			var triggers = [
				[ "clearEditor",           "after",  "triggerClearEditor"      ],
				[ "events.connect",        "after",  "triggerConnect"          ],
				[ "events.insertComment",  "before", "triggerInsertComment"    ],
				[ "events.deleteComment",  "before", "triggerDeleteComment"    ],
				[ "events.insertDocument", "before", "triggerDispDocumentList" ],
				[ "events.deleteDocument", "before", "triggerDispDocumentList" ],
				[ "events.insertComment",  "before", "triggerDispDocumentList" ],
				[ "events.deleteComment",  "before", "triggerDispDocumentList" ]
			];
			$.each(triggers, function (key, trigger) {
				self.oApp.insertTrigger(self, trigger[0], trigger[1], self[trigger[2]]);
			});
		}
		Plugin.prototype = {
			triggerClearEditor: function () {
				$("#rText").val("");
			},
			triggerConnect: function () {
				var once = this.connect;
					this.connect = true;
				if (once !== true) {
					var self = this;
					var cic = global.completeInsertComment;
					if (cic && $.isFunction(cic)) {
						global.completeInsertComment = function (obj) {
							self.oApp.clearEditor();
						};
					}
					var lp = global.loadPage;
					if (lp && $.isFunction(lp)) {
						global.loadPage = function (document_srl, page) {
							self.oApp.current_url = self.oApp.current_url
								.setQuery("document_srl", document_srl)
								.setQuery("cpage", page);
							lp(document_srl, page);
						};
					}
					$(".bd").on("click", ".auth .de", function () {
						var $this = $(this);
						var url = $this.attr("href");
						if (url.indexOf("#") > -1) {
							url = url.substring(0, url.indexOf("#"));
						}
						var comment_srl = url.getQuery("comment_srl");
						var callback = function (response, status, xhr) {
							alert(response.message);
						};
						var fallback = function (document_srl) {
							self.dispComment();
						};
						self.oApp.deleteComment(comment_srl, url, callback, fallback);
						return false;
					})
					.on("click", ".pn .prev, .pn .next", function () {
						var $this = $(this);
						var url = $this.attr("href");
						if (url.indexOf("#") > -1) {
							url = url.substring(0, url.indexOf("#"));
						}
						var page = url.getQuery("page");
						self.dispDocumentListByPage(page);
						return false;
					});
				}
			},
			triggerInsertComment: function (obj) {
				($("#cl [class*='comment_" + obj.parent_srl + "']").length ||
				 $(".co [class*='document_" + obj.parent_srl + "']").length) &&
					this.dispComment();
			},
			triggerDeleteComment: function (obj) {
				$("#cl [class*='comment_" + obj.target_srl + "']").length && this.dispComment();
			},
			triggerDispDocumentList: function () {
				$(".lt").length && this.dispDocumentList();
			},
			dispComment: function () {
				if (!$("#clb").length) {
					var stack = [];
					stack.push('<div class="hx h3">');
					stack.push('<h3 id="clb">' + global.xe.lang.cmd_reply + ' <em>[1]</em></h3>');
					stack.push('<button type="button" class="tg tgr" title="open/close"></button>');
					stack.push("</div>");
					$("#skip_co").after(stack.join("\n"));
				}
				var url = this.oApp.current_url;
				var document_srl = url.getQuery("document_srl");
				var page = url.getQuery("cpage");
				return loadPage(document_srl, page);
			},
			dispDocumentList: function (args) {
				var self = this;
				var handler = this.getPage(args);
					handler.done(function (response, status, xhr) {
						var $obj = $("<div>").append($.parseHTML(response)).find(".bd");
						var header = $obj.children(".hx").children("h2").html();
						var content = $obj.children(".lt").html();
						var footer = $obj.children(".pn").html();

						var $body = $(".bd");
						var $header = $body.children(".hx").children("h2");
						var $content = $body.children(".lt");
						var $footer = $body.children(".pn");

						$header.html(header);
						$content.html(content);
						$footer.html(footer);
					});

				return handler;
			},
			dispDocumentListByPage: function (page) {
				var self = this;
				var handler = this.dispDocumentList({page: page});
					handler.done(function (response, status, xhr) {
						self.oApp.current_url = self.oApp.current_url.setQuery("page", page);
					});

				return handler;
			},
			getPage: function (args) {
				return this.oApp.ajax("html", this.oApp.current_url, null, null, args);
			}
		};
		var register = new Plugin();
	});
})(this, jQuery);

/* End of file */
