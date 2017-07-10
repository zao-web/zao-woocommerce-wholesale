module.exports = {
	all: {
		files: {
			'assets/js/zao-woocommerce-wholesale.min.js': ['assets/js/zao-woocommerce-wholesale.js']
		},
		options: {
			// banner: '/*! <%= pkg.title %> - v<%= pkg.version %>\n * <%= pkg.homepage %>\n * Copyright (c) <%= grunt.template.today("yyyy") %>\n * Licensed GPLv2+\n */\n',

			banner: '/*! <%= pkg.title %> - v<%= pkg.version %> - <%= grunt.template.today("yyyy-mm-dd") %>' +
			' | <%= pkg.homepage %>' +
			' | Copyright (c) <%= grunt.template.today("yyyy") %> <%= pkg.author.name %>' +
			' | Licensed <%= pkg.license %>' +
			' */\n'
		}
	}
};
