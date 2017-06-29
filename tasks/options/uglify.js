module.exports = {
	all: {
		files: {
			'assets/js/zao-woocommerce-wholesale.min.js': ['assets/js/zao-woocommerce-wholesale.js']
		},
		options: {
			banner: '/*! <%= pkg.title %> - v<%= pkg.version %>\n * <%= pkg.homepage %>\n * Copyright (c) <%= grunt.template.today("yyyy") %>\n * Licensed GPLv2+\n */\n',
			mangle: {
				except: ['jQuery']
			}
		}
	}
};
