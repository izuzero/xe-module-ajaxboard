/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @brief  AJAXBOARD for NODE.JS (WSPS using SOCKET.IO)
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 */

"use strict";

process.on("uncaughtException", function(e) {
	console.error("Caught exception:", e);
});

var config = {
	SERVER_PORT    : (process.argv[2] || 3000),
	REDIS_PORT     : (process.argv[3] || 6379),
	REDIS_HOST     : (process.argv[4] || "127.0.0.1"),
	REDIS_PASSWORD : (process.argv[5] || "")
};

var oModule = {
	OS           : require("os"),
	NET          : require("net"),
	HTTP         : require("http"),
	CLUSTER      : require("cluster"),
	SIO          : require("socket.io"),
	REDIS        : require("socket.io-redis/node_modules/redis").createClient,
	REDISADAPTER : require("socket.io-redis")
};

/**
 * @brief Calculate hash
 * @param array ip
 * @param integer seed (Optical)
 * @return integer
 */
var hash = function(ip, seed) {
	var hash = ip.reduce(function(r, num) {
		r += parseInt(num, 10);
		r %= 0x80000000;
		r += r << 10;
		r %= 0x80000000;
		r ^= r >> 6;
		return r;
	}, seed);

	hash += hash << 3;
	hash %= 0x80000000;
	hash ^= hash >> 11;
	hash += hash << 15;
	hash %= 0x80000000;
	return hash >>> 0;
};

/**
 * @brief Get room string from object
 * @param object obj
 * @return string
 */
var getRoomKey = function(obj) {
	var queue = [],
		sorted = (function(obj) {
			var queue = [], sorted = {};
			for (var key in obj) {
				if (obj.hasOwnProperty(key)) {
					queue.push(key);
				}
			}
			queue.sort();
			for (var i = 0; i < queue.length; i++) {
				sorted[queue[i]] = obj[queue[i]];
			}
			return sorted;
		})(obj);
	for (var key in sorted) {
		if (sorted.hasOwnProperty(key)) {
			queue.push(String(key) + ":" + String(sorted[key]));
		}
	}
	return queue.join(":");
};

/**
 * @brief Create Websocket Push Server
 * @param object SERV
 * @param string REDIS_HOST
 * @param integer REDIS_PORT
 * @return object
 */
var createWSPS = function(SERV, REDIS_HOST, REDIS_PORT, REDIS_PASSWORD) {
	var opts = {detect_buffers: true};
	if (REDIS_PASSWORD)
		opts.auth_pass = REDIS_PASSWORD;

	var pub = oModule.REDIS(REDIS_PORT, REDIS_HOST, opts),
		sub = oModule.REDIS(REDIS_PORT, REDIS_HOST, opts),
		redis = oModule.REDISADAPTER({pubClient: pub, subClient: sub});

	var WSPS = oModule.SIO.listen(SERV);
		WSPS.adapter(redis);
		WSPS.serveClient(false);
		WSPS.on("connection", function(socket) {
			var member_srl = socket.handshake.query.member_srl;
			if (member_srl) {
				var opts = {member_srl: member_srl};
				socket.join(getRoomKey(opts));
			}
		});

	return WSPS;
};

/**
 * @brief Create Server
 * @param string REDIS_HOST
 * @param integer REDIS_PORT
 * @return object
 */
var createServ = function(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD) {
	var SERV;
	if (oModule.CLUSTER.isMaster) {
		var workers = [],
			numCPUs = oModule.OS.cpus().length,
			seed = ~~(Math.random() * 1e9),
			hash = function(ip, seed) {
				var hash = ip.reduce(function(r, num) {
					r += parseInt(num, 10);
					r %= 0x80000000;
					r += r << 10;
					r %= 0x80000000;
					r ^= r >> 6;
					return r;
				}, seed);

				hash += hash << 3;
				hash %= 0x80000000;
				hash ^= hash >> 11;
				hash += hash << 15;
				hash %= 0x80000000;
				return hash >>> 0;
			},
			createWorker = function(idx) {
				(workers[idx] = oModule.CLUSTER.fork())
					.on("exit", function(worker, code, signal) {
						console.error("[WORKER:DIED]", worker.process.pid, code, signal);
						createWorker(idx);
					});
				console.info("[WORKER:CREATED]", workers[idx].process.pid);
			};
		for (var i = 0; i < numCPUs; i++) {
			createWorker(i);
		}
		SERV = oModule.NET.createServer(function(req) {
			var ipHash = hash((req.remoteAddress || "").split(/\./g), seed),
				worker = workers[ipHash % workers.length];
				worker.send("session:connection", req);
		});
	}
	else {
		SERV = oModule.HTTP.createServer();
		SERV.listen = (function(old) {return (function() {return old.call(this)})})(SERV.listen);
		createWSPS(SERV, REDIS_HOST, REDIS_PORT, REDIS_PASSWORD);

		process.on("message", function(message, req) {
			if (message === "session:connection")
				SERV.emit("connection", req);
		});
	}

	return SERV;
};

createServ(
	config.REDIS_HOST,
	config.REDIS_PORT,
	config.REDIS_PASSWORD
)
.listen(config.SERVER_PORT, function() {
	console.info("Master process is running port", config.SERVER_PORT);
});

/* End of file */
