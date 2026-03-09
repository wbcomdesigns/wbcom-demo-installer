'use strict';
module.exports = function (grunt) {

	// Load all grunt tasks matching the `grunt-*` pattern.
	require('load-grunt-tasks')(grunt);

	grunt.initConfig(
		{
			// Load package.json
			pkg: grunt.file.readJSON('package.json'),

			// Check text domain
			checktextdomain: {
				options: {
					text_domain: ['wbcom-theme-demo-installer'],
					keywords: [
						'__:1,2d',
						'_e:1,2d',
						'_x:1,2c,3d',
						'esc_html__:1,2d',
						'esc_html_e:1,2d',
						'esc_html_x:1,2c,3d',
						'esc_attr__:1,2d',
						'esc_attr_e:1,2d',
						'esc_attr_x:1,2c,3d',
						'_ex:1,2c,3d',
						'_n:1,2,4d',
						'_nx:1,2,4c,5d',
						'_n_noop:1,2,3d',
						'_nx_noop:1,2,3c,4d'
					]
				},
				target: {
					files: [{
						src: [
							'*.php',
							'core/*.php',
							'includes/*.php',
							'!node_modules/**',
							'!update-checker/**',
							'!core/class-tgm-plugin-activation.php'
						],
						expand: true
					}]
				}
			},

			// Task for CSS minification
			cssmin: {
				assets: {
					files: [{
						expand: true,
						cwd: 'assets/css/',
						src: ['*.css', '!*.min.css'],
						dest: 'assets/css/',
						ext: '.min.css'
					}]
				}
			},

			// Task for JavaScript minification
			uglify: {
				assets: {
					options: {
						mangle: false
					},
					files: [{
						expand: true,
						cwd: 'assets/js/',
						src: ['*.js', '!*.min.js'],
						dest: 'assets/js/',
						ext: '.min.js'
					}]
				}
			},

			// Task for watching file changes
			watch: {
				css: {
					files: ['assets/css/*.css', '!assets/css/*.min.css'],
					tasks: ['cssmin']
				},
				js: {
					files: ['assets/js/*.js', '!assets/js/*.min.js'],
					tasks: ['uglify']
				}
			},

			// Make POT file
			makepot: {
				target: {
					options: {
						cwd: '.',
						domainPath: 'language/',
						exclude: ['node_modules/*', 'update-checker/*', 'core/class-tgm-plugin-activation.php'],
						mainFile: 'wbcom-theme-demo-installer.php',
						potFilename: 'wbcom-theme-demo-installer.pot',
						potHeaders: {
							poedit: true,
							'Last-Translator': 'Varun Dubey',
							'Language-Team': 'Wbcom Designs',
							'report-msgid-bugs-to': '',
							'x-poedit-keywordslist': true
						},
						type: 'wp-plugin',
						updateTimestamp: true
					}
				}
			},

			// Compress files for distribution
			compress: {
				// Public distribution - clean plugin only
				dist: {
					options: {
						archive: 'dist/wbcom-demo-installer-<%= pkg.version %>.zip',
						mode: 'zip'
					},
					files: [{
						expand: true,
						src: [
							'**/*',
							// Exclude development files
							'!node_modules/**',
							'!.git/**',
							'!.gitignore',
							'!.gitattributes',
							'!*.log',
							'!gruntfile.js',
							'!Gruntfile.js',
							'!package.json',
							'!package-lock.json',
							'!.editorconfig',
							'!.jshintrc',
							'!.DS_Store',
							'!Thumbs.db',
							'!*.zip',
							'!*.map',
							'!**/*.map',
							'!*.scss',
							'!**/*.scss',
							'!sass/**',
							'!*.sublime-project',
							'!*.sublime-workspace',
							'!.idea/**',
							'!.vscode/**',
							'!tests/**',
							'!bin/**',
							'!build/**',
							'!dist/**',
							'!tmp/**',
							// Exclude markdown files
							'!*.md',
							'!**/*.md',
							// Exclude docs and marketing
							'!docs/**',
							'!marketing/**',
							// Exclude sales page
							'!sales-page.html',
							// Exclude CLAUDE.md
							'!CLAUDE.md'
						],
						dest: 'wbcom-demo-installer/'
					}]
				},
				// Internal distribution - includes docs for team use
				internal: {
					options: {
						archive: 'dist/wbcom-demo-installer-<%= pkg.version %>-internal.zip',
						mode: 'zip'
					},
					files: [{
						expand: true,
						src: [
							'**/*',
							// Exclude development files only
							'!node_modules/**',
							'!.git/**',
							'!.gitignore',
							'!.gitattributes',
							'!*.log',
							'!gruntfile.js',
							'!Gruntfile.js',
							'!package.json',
							'!package-lock.json',
							'!.editorconfig',
							'!.jshintrc',
							'!.DS_Store',
							'!Thumbs.db',
							'!*.zip',
							'!*.map',
							'!**/*.map',
							'!*.scss',
							'!**/*.scss',
							'!sass/**',
							'!*.sublime-project',
							'!*.sublime-workspace',
							'!.idea/**',
							'!.vscode/**',
							'!tests/**',
							'!bin/**',
							'!build/**',
							'!dist/**',
							'!tmp/**'
						],
						dest: 'wbcom-demo-installer/'
					}]
				}
			}
		}
	);

	// Register default task
	grunt.registerTask('default', ['cssmin', 'uglify', 'checktextdomain', 'makepot', 'watch']);

	// Build task - generates everything needed for production
	grunt.registerTask('build', [
		'cssmin',
		'uglify',
		'checktextdomain',
		'makepot'
	]);

	// Development task
	grunt.registerTask('dev', ['watch']);

	// Production task - build + create public distribution zip
	grunt.registerTask('production', [
		'checktextdomain',
		'cssmin',
		'uglify',
		'makepot',
		'compress:dist'
	]);

	// Alias for backward compatibility
	grunt.registerTask('package', ['production']);

	// Distribution task - creates both public and internal zips
	grunt.registerTask('dist', [
		'checktextdomain',
		'cssmin',
		'uglify',
		'makepot',
		'compress:dist',
		'compress:internal'
	]);

	// Quick dist - just create zips without rebuilding assets
	grunt.registerTask('dist:quick', [
		'compress:dist',
		'compress:internal'
	]);

	// Translation task
	grunt.registerTask('i18n', [
		'makepot'
	]);
};
