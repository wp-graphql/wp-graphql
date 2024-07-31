/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./packages/extensions/Extensions.js":
/*!*******************************************!*\
  !*** ./packages/extensions/Extensions.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _PluginCard__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./PluginCard */ "./packages/extensions/PluginCard.js");





/**
 * Extensions component to display the list of WPGraphQL extensions.
 *
 * @return {JSX.Element} The Extensions component.
 */
const Extensions = () => {
  const [extensions, setExtensions] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (window.wpgraphqlExtensions && window.wpgraphqlExtensions.extensions) {
      setExtensions(window.wpgraphqlExtensions.extensions);
    }
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wp-clearfix"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "plugin-cards"
  }, extensions.map(extension => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_PluginCard__WEBPACK_IMPORTED_MODULE_2__["default"], {
    key: extension.plugin_url,
    plugin: extension
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Extensions);

/***/ }),

/***/ "./packages/extensions/PluginCard.js":
/*!*******************************************!*\
  !*** ./packages/extensions/PluginCard.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _useInstallPlugin__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./useInstallPlugin */ "./packages/extensions/useInstallPlugin.js");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./utils */ "./packages/extensions/utils.js");






const PluginCard = ({
  plugin
}) => {
  const {
    installing,
    activating,
    status,
    error,
    installPlugin,
    activatePlugin
  } = (0,_useInstallPlugin__WEBPACK_IMPORTED_MODULE_4__["default"])(plugin.plugin_url, plugin.plugin_path);
  const [isInstalled, setIsInstalled] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(plugin.installed);
  const [isActive, setIsActive] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(plugin.active);
  const [isErrorVisible, setIsErrorVisible] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(true);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    setIsInstalled(plugin.installed);
    setIsActive(plugin.active);
  }, [plugin]);
  const handleButtonClick = async () => {
    const prevInstalled = isInstalled;
    const prevActive = isActive;
    try {
      if (!isInstalled) {
        await installPlugin();
        setIsInstalled(true);
        setIsActive(true); // Assume successful activation after installation
      } else {
        await activatePlugin(plugin.plugin_path);
        setIsActive(true);
      }
    } catch (err) {
      setIsInstalled(prevInstalled);
      setIsActive(prevActive);
    } finally {
      // Ensure the extension status in the global window object is updated
      window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map(extension => extension.plugin_url === plugin.plugin_url ? {
        ...extension,
        installed: isInstalled,
        active: isActive
      } : extension);
    }
  };
  const host = new URL(plugin.plugin_url).host;
  const {
    buttonText,
    buttonDisabled
  } = (0,_utils__WEBPACK_IMPORTED_MODULE_5__.getButtonDetails)(host, plugin.plugin_url, isInstalled, isActive, installing, activating);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "plugin-card"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "plugin-card-top"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "name column-name"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", null, plugin.name), plugin.experiment && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("em", {
    className: "plugin-experimental"
  }, "(experimental)")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "action-links"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "plugin-action-buttons"
  }, host.includes('wordpress.org') && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    className: `button ${isActive ? 'button-disabled' : 'button-primary'}`,
    disabled: buttonDisabled,
    onClick: handleButtonClick
  }, buttonText, (installing || activating) && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null))), host.includes('github.com') && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: plugin.plugin_url,
    target: "_blank",
    rel: "noopener noreferrer",
    className: "button button-secondary"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('View on GitHub', 'wp-graphql'))), plugin.support_url && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: plugin.support_url,
    target: "_blank",
    rel: "noopener noreferrer",
    className: "thickbox open-plugin-details-modal"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Get Support', 'wp-graphql'))), plugin.settings_link && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: plugin.settings_link
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Settings', 'wp-graphql'))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "desc column-description"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, plugin.description))), error && isErrorVisible && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: "error",
    isDismissible: true,
    onRemove: () => setIsErrorVisible(false)
  }, error));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PluginCard);

/***/ }),

/***/ "./packages/extensions/useInstallPlugin.js":
/*!*************************************************!*\
  !*** ./packages/extensions/useInstallPlugin.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);



const useInstallPlugin = (pluginUrl, pluginPath) => {
  const [installing, setInstalling] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [activating, setActivating] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [status, setStatus] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [error, setError] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');

  // Helper function to update the status and error states
  const updateStatus = (newStatus, newError = '') => {
    setStatus(newStatus);
    setError(newError);
  };

  // Helper function to update the plugin's activation state in wpgraphqlExtensions
  const updateExtensionStatus = isActive => {
    window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map(extension => extension.plugin_url === pluginUrl ? {
      ...extension,
      installed: true,
      active: isActive
    } : extension);
  };
  const activatePlugin = async (path = pluginPath) => {
    setActivating(true);
    updateStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activating...', 'wp-graphql'));
    if (!path) {
      let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
      path = `${slug}/${slug}.php`;
    }
    try {
      const activateResult = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/wp/v2/plugins/${path}`,
        method: 'PUT',
        data: {
          status: 'active'
        },
        headers: {
          'X-WP-Nonce': wpgraphqlExtensions.nonce
        }
      });
      const jsonResponse = activateResult;
      if (jsonResponse.status === 'active') {
        updateStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'wp-graphql'));
        updateExtensionStatus(true);
        return true; // Indicate success
      } else if (jsonResponse.message.includes('Plugin file does not exist')) {
        throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Plugin file does not exist', 'wp-graphql'));
      } else {
        throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'));
      }
    } catch (err) {
      updateStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'), err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'));
      throw err; // Re-throw error to handle in PluginCard
    } finally {
      setInstalling(false);
      setActivating(false);
    }
  };
  const installPlugin = async () => {
    setInstalling(true);
    updateStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installing...', 'wp-graphql'));
    let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
    try {
      const installResult = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/wp/v2/plugins',
        method: 'POST',
        data: {
          slug: slug,
          status: 'inactive'
        },
        headers: {
          'X-WP-Nonce': wpgraphqlExtensions.nonce
        }
      });
      if (installResult.status !== 'inactive') {
        throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installation failed', 'wp-graphql'));
      }
      await activatePlugin(pluginPath);
    } catch (err) {
      if (err.message.includes('destination folder already exists')) {
        await activatePlugin(pluginPath);
      } else {
        updateStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installation failed', 'wp-graphql'), err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installation failed', 'wp-graphql'));
        setInstalling(false);
        throw err; // Re-throw error to handle in PluginCard
      }
    }
  };
  return {
    installing,
    activating,
    status,
    error,
    installPlugin,
    activatePlugin
  };
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (useInstallPlugin);

/***/ }),

/***/ "./packages/extensions/utils.js":
/*!**************************************!*\
  !*** ./packages/extensions/utils.js ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getButtonDetails: () => (/* binding */ getButtonDetails)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);


/**
 * Returns the details for the button based on the plugin status and host.
 *
 * @param {string} host - The host name where the plugin is being used.
 * @param {string} plugin_url - The URL of the plugin.
 * @param {boolean} isInstalled - Whether the plugin is installed.
 * @param {boolean} isActive - Whether the plugin is active.
 * @param {boolean} installing - Whether the plugin is currently being installed.
 * @param {boolean} activating - Whether the plugin is currently being activated.
 * @param {Function} activatePlugin - Function to activate the plugin.
 * @returns {{buttonText: string, buttonDisabled: boolean, buttonOnClick: Function|null}} The button details.
 */
const getButtonDetails = (host, plugin_url, isInstalled, isActive, installing, activating, activatePlugin) => {
  let buttonText;
  let buttonDisabled = false;
  let buttonOnClick = null;

  /**
   * Opens a new browser window with the specified URL.
   *
   * @param {string} url - The URL to open.
   * @returns {Function} A function that opens the URL in a new window.
   */
  const openLink = url => () => window.open(url, '_blank');
  if (installing) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Installing...', 'wp-graphql');
    buttonDisabled = true;
  } else if (activating) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Activating...', 'wp-graphql');
    buttonDisabled = true;
  } else if (isActive) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Active', 'wp-graphql');
    buttonDisabled = true;
  } else if (isInstalled) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Activate', 'wp-graphql');
    buttonOnClick = activatePlugin;
  } else {
    const domain = new URL(plugin_url).hostname.toLowerCase();
    switch (true) {
      case /github\.com$/.test(domain):
        buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on GitHub', 'wp-graphql');
        buttonOnClick = openLink(plugin_url);
        break;
      case /bitbucket\.org$/.test(domain):
        buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on Bitbucket', 'wp-graphql');
        buttonOnClick = openLink(plugin_url);
        break;
      case /gitlab\.com$/.test(domain):
        buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on GitLab', 'wp-graphql');
        buttonOnClick = openLink(plugin_url);
        break;
      case /wordpress\.org$/.test(domain):
        buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Install & Activate', 'wp-graphql');
        buttonOnClick = activatePlugin;
        break;
      default:
        buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View Plugin', 'wp-graphql');
        buttonOnClick = openLink(plugin_url);
    }
  }
  return {
    buttonText,
    buttonDisabled,
    buttonOnClick
  };
};

/***/ }),

/***/ "./packages/extensions/index.scss":
/*!****************************************!*\
  !*** ./packages/extensions/index.scss ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**************************************!*\
  !*** ./packages/extensions/index.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _Extensions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Extensions */ "./packages/extensions/Extensions.js");
/* harmony import */ var _index_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./index.scss */ "./packages/extensions/index.scss");



document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('wpgraphql-extensions');
  if (container) {
    const root = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(container);
    root.render((0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_Extensions__WEBPACK_IMPORTED_MODULE_1__["default"]));
  }
});
})();

/******/ })()
;
//# sourceMappingURL=extensions.js.map