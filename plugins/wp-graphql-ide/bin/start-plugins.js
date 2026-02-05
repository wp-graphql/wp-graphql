#!/usr/bin/env node

const { execSync } = require('child_process');
const path = require('path');
const glob = require('glob');

// Define the path to the plugins directory
const pluginsPath = path.resolve(__dirname, '../plugins');

// Use glob to find all plugin directories
const plugins = glob.sync(`${pluginsPath}/*`);

// Function to start a plugin using wp-scripts
const startPlugin = (plugin) => {
	const pluginName = path.basename(plugin);
	console.log(`Starting plugin: ${pluginName}`);
	execSync('wp-scripts start', {
		cwd: plugin,
		stdio: 'inherit',
	});
};

// Start each plugin
plugins.forEach(startPlugin);
