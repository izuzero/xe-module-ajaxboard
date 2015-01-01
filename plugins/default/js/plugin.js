/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */

(function (global, $) {
	"use strict";
	var core = global.ajaxboard;
	function getPage(args) {
		return core.ajax("html", core.current_url, null, null, args);
	}
	function dispCommentList(args) {
		var handler = getPage(args);
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
	}
	function dispCommentListByCpage(cpage) {
		var handler = dispCommentList({cpage: cpage});
			handler.done(function (response, status, xhr) {
				core.current_url = core.current_url.setQuery("cpage", cpage);
			});

		return handler;
	}
	function dispDocumentList(args) {
		var handler = getPage(args);
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
	}
	function dispDocumentListByPage(page) {
		var handler = dispDocumentList({page: page});
			handler.done(function (response, status, xhr) {
				core.current_url = core.current_url.setQuery("page", page);
			});

		return handler;
	}
	var called = false;
	core.insertTrigger("events.connect", "after", function (type) {
		if (called) return;
		called = true;
		var cic = global.completeInsertComment;
		if (cic && $.isFunction(cic)) {
			global.completeInsertComment = function (obj) {
				core.clearEditor();
			};
		}
		$(".read_footer").on("click", ".btnArea [href]", function () {
			var href = $(this).attr("href");
			if (href.indexOf("#") > -1) {
				href = href.substring(0, href.indexOf("#"));
			}
			var act = href.getQuery("act");
			var document_srl = href.getQuery("document_srl");
			var redirect_url = core.current_url
				.setQuery("act", "")
				.setQuery("document_srl", "");
			if (act == "dispBoardDelete") {
				core.deleteDocument(
					document_srl,
					href,
					function (response, status, xhr) {
						alert(response.message);
						location.href = redirect_url;
					},
					function (document_srl) {
						location.href = redirect_url;
					}
				);
				return false;
			}
		});
		$("#comment").on("click", ".action .delete", function () {
			var href = $(this).attr("href");
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
					dispCommentList();
				}
			);
			return false;
		})
		.on("click", ".pagination [href]", function () {
			var $this = $(this);
			var href = $this.attr("href");
			if (href.indexOf("#") > -1) {
				href = href.substring(0, href.indexOf("#"));
			}
			var cpage = href.getQuery("cpage");
			dispCommentListByCpage(cpage > 1 && $this.hasClass("direction") ? "" : cpage);
			return false;
		});
		$(".list_footer").on("click", ".pagination [href]", function () {
			var href = $(this).attr("href");
			if (href.indexOf("#") > -1) {
				href = href.substring(0, href.indexOf("#"));
			}
			dispDocumentListByPage(href.getQuery("page"));
			return false;
		});
	});
	core.insertTrigger("events.insertComment", "before", function (obj) {
		$("#comment").length &&
		($("#comment_" + obj.parent_srl).length ||
		 $(".read_body [class*='document_" + obj.parent_srl + "']").length) &&
			dispCommentList();
	});
	core.insertTrigger("events.deleteComment", "before", function (obj) {
		$("#comment_" + obj.target_srl).length &&
			dispCommentList();
	});
})(this, jQuery);

/* End of file */
