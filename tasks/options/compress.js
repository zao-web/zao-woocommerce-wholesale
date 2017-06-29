module.exports = {
	main: {
		options: {
			mode: 'zip',
			archive: './release/zwoowh.<%= pkg.version %>.zip'
		},
		expand: true,
		cwd: 'release/<%= pkg.version %>/',
		src: ['**/*'],
		dest: 'zwoowh/'
	}
};