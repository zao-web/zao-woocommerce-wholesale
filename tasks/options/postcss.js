module.exports = {
	dist: {
		options: {
			processors: [
				require('autoprefixer')({browsers: 'last 2 versions'})
			]
		},
		files: { 
			'assets/css/zao-woocommerce-wholesale.css': [ 'assets/css/zao-woocommerce-wholesale.css' ]
		}
	}
};