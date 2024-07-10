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
    if (!isInstalled) {
      await installPlugin();
    } else {
      await activatePlugin();
    }
    setIsInstalled(true);
    setIsActive(true);
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
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('View on GitHub', 'wp-graphql'))), plugin.support_link && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: plugin.support_link,
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
  const installPlugin = async () => {
    setInstalling(true);
    setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installing...', 'wp-graphql'));
    setError('');
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
        setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installation failed', 'wp-graphql'));
        setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Installation failed', 'wp-graphql'));
        setInstalling(false);
      }
    }
  };
  const activatePlugin = async (path = null) => {
    setActivating(true);
    setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activating...', 'wp-graphql'));
    setError('');
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
        setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'wp-graphql'));
        window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map(extension => extension.plugin_url === pluginUrl ? {
          ...extension,
          installed: true,
          active: true
        } : extension);
      } else if (jsonResponse.message.includes('Plugin file does not exist')) {
        // The plugin is already activated
        setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'wp-graphql'));
        setError('');
        window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map(extension => extension.plugin_url === pluginUrl ? {
          ...extension,
          installed: true,
          active: true
        } : extension);
      } else {
        throw new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'));
      }
    } catch (err) {
      setStatus((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'));
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Activation failed', 'wp-graphql'));
    } finally {
      setInstalling(false);
      setActivating(false);
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

const getButtonDetails = (host, plugin_url, isInstalled, isActive, installing, activating, activatePlugin) => {
  let buttonText;
  let buttonDisabled = false;
  let buttonOnClick;
  if (host.includes('github.com')) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on GitHub', 'wp-graphql');
    buttonOnClick = () => window.open(plugin_url, '_blank');
  } else if (host.includes('bitbucket.org')) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on Bitbucket', 'wp-graphql');
    buttonOnClick = () => window.open(plugin_url, '_blank');
  } else if (host.includes('gitlab.com')) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View on GitLab', 'wp-graphql');
    buttonOnClick = () => window.open(plugin_url, '_blank');
  } else if (installing) {
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
  } else if (host.includes('wordpress.org')) {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Install & Activate', 'wp-graphql');
    buttonOnClick = activatePlugin;
  } else {
    buttonText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View Plugin', 'wp-graphql');
    buttonOnClick = () => window.open(plugin_url, '_blank');
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