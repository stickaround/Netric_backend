/**
 * @fileOverview Object represents the netric account object
 *
 * @author:	Sky Stebnicki, sky.stebnicki@aereus.com; 
 * 			Copyright (c) 2014 Aereus Corporation. All rights reserved.
 */
alib.declare("netric.account.Account");
alib.require("netric");
alib.require("netric.module.loader");
alib.require("netric.User");

/**
 * Make sure account namespace is initialized
 */
netric.account = netric.account || {};

/**
 * Account instance
 *
 * @param {Object} opt_data Optional data used to initialize the account
 */
netric.account.Account = function(opt_data)
{
	// Initialize empty object if opt_data was not set
	var initData = opt_data || new Object();

	/**
	 * Account ID
	 *
	 * @public
	 * @type {string}
	 */
	this.id = initData.id || "";

	/**
	 * Unique account name
	 *
	 * @public
	 * @type {string}
	 */
	this.name = initData.name || "";

	/**
	 * Organization name
	 * 
	 * @public
	 * @type {string}
	 */
	this.orgName = initData.orgName || "";

	/**
	 * Currently authenticated user
	 * 
	 * @public
	 * @type {netric.User}
	 */
	this.user = (initData.user) ? new netric.User(initData.user) : null;

	/**
	 * If modules have been pre-loaded in the application data then set
	 */
	if (initData.modules)
		netric.module.loader.preloadFromData(initData.modules);
}