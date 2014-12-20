/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	global.ajaxboardStorage.connect = true;
	$(function () {
		function Plugin() {
			this.oApp = global.ajaxboard;
			var self = this;
			var triggers = [
				[ "events.connect",        "after",  "triggerAfterConnect"     ],
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
			triggerAfterConnect: function () {
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
					$(".read_footer").on("click", ".btnArea [href]", function () {
						var src = $(this).attr("href");
						if (src.indexOf("#") > -1) {
							src = src.substring(0, src.indexOf("#"));
						}
						var act = src.getQuery("act");
						var document_srl = src.getQuery("document_srl");
						var redirect_url = self.oApp.current_url
							.setQuery("act", "")
							.setQuery("document_srl", "");
						if (act == "dispBoardDelete") {
							var callback = function (response, status, xhr) {
								alert(response.message);
								location.href = redirect_url;
							};
							var fallback = function (document_srl) {
								location.href = redirect_url;
							};
							self.oApp.deleteDocument(document_srl, src, callback, fallback);
							return false;
						}
					});
					$("#comment").on("click", ".action .delete", function () {
						var src = $(this).attr("href");
						if (src.indexOf("#") > -1) {
							src = src.substrin(0, src.indexOf("#"));
						}
						var comment_srl = src.getQuery("comment_srl");
						var callback = function (response, status, xhr) {
							alert(response.message);
						};
						var fallback = function (document_srl) {
							self.dispCommentList();
						};
						self.oApp.deleteDocument(comment_srl, src, callback, fallback);
						return false;
					})
					.on("click", ".pagination [href]", function () {
						var $this = $(this);
						var src = $this.attr("href");
						if (src.indexOf("#") > -1) {
							src = src.substring(0, src.indexOf("#"));
						}
						var cpage = src.getQuery("cpage");
						self.dispCommentListByCpage(cpage > 1 && $this.hasClass("direction") ? "" : cpage);
						return false;
					});
					$(".list_footer").on("click", ".pagination [href]", function () {
						var src = $(this).attr("href");
						if (src.indexOf("#") > -1) {
							src = src.substring(0, src.indexOf("#"));
						}
						self.dispDocumentListByPage(src.getQuery("page"));
						return false;
					});
				}
			},
			triggerInsertComment: function (obj) {
				$("#comment").length &&
				($("#comment_" + obj.parent_srl).length ||
				 $(".read_body [class*='document_" + obj.parent_srl + "']").length) &&
					this.dispCommentList();
			},
			triggerDeleteComment: function (obj) {
				$("#comment_" + obj.target_srl).length && this.dispCommentList();
			},
			triggerDispDocumentList: function () {
				this.dispDocumentList();
			},
			dispCommentList: function (args) {
				var self = this;
				var handler = this.getPage(args);
					handler.done(function (response, status, xhr) {
						var $obj = $("<div>").append($.parseHTML(response)).find("#comment");
						var header = $obj.children(".fbHeader").html();
						var content = $obj.children(".fbList").html();
						var pagination = $obj.children(".pagination").html();

						var $body = $("#comment");
						var $header = $body.children(".fbHeader");
						var $content = $body.children(".fbList");
						var $pagination = $body.children(".pagination");

						if ($content.length) {
							if (!content) {
								$content.remove();
							}
						}
						else {
							$header.after($('<ul class="fbList">'));
						}
						if ($pagination.length) {
							if (!pagination) {
								$pagination.remove();
							}
						}
						else if (pagination) {
							$content.after($('<div class="pagination">'));
						}

						$body.children(".fbHeader").html(header);
						$body.children(".fbList").html(content);
						$body.children(".pagination").html(pagination);
					});

				return handler;
			},
			dispCommentListByCpage: function (cpage) {
				var self = this;
				var handler = this.dispCommentList({cpage: cpage});
					handler.done(function (response, status, xhr) {
						self.oApp.current_url = self.oApp.current_url.setQuery("cpage", cpage);
					});

				return handler;
			},
			dispDocumentList: function (args) {
				var self = this;
				var handler = this.getPage(args);
					handler.done(function (response, status, xhr) {
						var $obj = $("<div>").append($.parseHTML(response)).find(".board");
						var content = $obj.children(".board_list").html();
						var pagination = $obj.children(".list_footer").children(".pagination").html();

						var $body = $(".board");
						var $content = $body.children(".board_list");
						var $pagination = $body.children(".list_footer").children(".pagination");

						if ($pagination.length) {
							if (!pagination) {
								$pagination.remove();
							}
						}
						else if (pagination) {
							$(".list_footer").prepend('<div class="pagination">');
						}

						$content.html(content);
						$body.children(".list_footer").children(".pagination").html(pagination);
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
