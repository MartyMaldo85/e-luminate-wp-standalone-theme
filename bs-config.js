/**
 * Browser-sync dev proxy (npm run dev).
 *
 * Forwards the browser Host to WordPress so theme URL overrides can detect :3000,
 * and skips live-reload snippet injection in wp-admin (breaks block editor scripts).
 */
module.exports = {
	proxy: {
		target: 'http://localhost:8888/wordpress/',
		proxyReq: [
			function (proxyReq, req) {
				if (req.headers.host) {
					proxyReq.setHeader('X-Forwarded-Host', req.headers.host);
				}
			},
		],
	},
	files: ['**/*'],
	ignore: ['node_modules/**', '.git/**', 'vendor/**', '*.log'],
	snippetOptions: {
		blacklist: ['**/wp-admin/**', '**/wp-login.php'],
	},
};
