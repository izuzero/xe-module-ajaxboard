/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	var core = global.ajaxboard;
	function getPage(args) {
		return core.ajax("html", core.current_url, null, null, args);
	}
	function dispComment() {
		if (!$("#clb").length) {
			var stack = [];
			stack.push('<div class="hx h3">');
			stack.push('<h3 id="clb">' + global.xe.lang.cmd_reply + ' <em>[1]</em></h3>');
			stack.push('<button type="button" class="tg tgr" title="open/close"></button>');
			stack.push("</div>");
			$("#skip_co").after(stack.join("\n"));
		}
		var url = core.current_url;
		var document_srl = url.getQuery("document_srl");
		var page = url.getQuery("cpage");
		return loadPage(document_srl, page);
	}
	function dispDocumentList(args) {
		var handler = getPage(args);
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
	}
	function dispDocumentListByPage(page) {
		var handler = dispDocumentList({page: page});
			handler.done(function (response, status, xhr) {
				core.current_url = core.current_url.setQuery("page", page);
			});

		return handler;
	}
	function triggerDispDocumentList() {
		$(".lt").length &&
			dispDocumentList();
	}
	var called = false;
	core.insertTrigger("clearEditor", "after", function () {
		$("#rText").val("");
	});
	core.insertTrigger("events.connect", "after", function (type) {
		if (called) return;
		called = true;
		var cic = global.completeInsertComment;
		if (cic && $.isFunction(cic)) {
			global.completeInsertComment = function (obj) {
				core.clearEditor();
			};
		}
		var lp = global.loadPage;
		if (lp && $.isFunction(lp)) {
			global.loadPage = function (document_srl, page) {
				core.current_url = core.current_url
					.setQuery("document_srl", document_srl)
					.setQuery("cpage", page);
				lp(document_srl, page);
			};
		}
		$(".bd").on("click", ".auth .de", function () {
			var $this = $(this);
			var href = $this.attr("href");
			if (href.indexOf("#") > -1) {
				href = href.substring(0, href.indexOf("#"));
			}
			var comment_srl = href.getQuery("comment_srl");
			core.deleteComment(
				comment_srl,
				href,
				function (response, status, xhr) {
					alert(response.message);
				},
				function (document_srl) {
					dispComment();
				}
			);
			return false;
		})
		.on("click", ".pn .prev, .pn .next", function () {
			var $this = $(this);
			var href = $this.attr("href");
			if (href.indexOf("#") > -1) {
				href = href.substring(0, href.indexOf("#"));
			}
			var page = href.getQuery("page");
			dispDocumentListByPage(page);
			return false;
		});
	});
	core.insertTrigger("events.insertDocument", "before", triggerDispDocumentList);
	core.insertTrigger("events.deleteDocument", "before", triggerDispDocumentList);
	core.insertTrigger("events.insertComment", "before", function (obj) {
		triggerDispDocumentList();
		($("#cl [class*='comment_" + obj.parent_srl + "']").length ||
		 $(".co [class*='document_" + obj.parent_srl + "']").length) &&
			dispComment();
	});
	core.insertTrigger("events.deleteComment", "before", function (obj) {
		triggerDispDocumentList();
		$("#cl [class*='comment_" + obj.target_srl + "']").length &&
			dispComment();
	});
})(this, jQuery);

/* End of file */
