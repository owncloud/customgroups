/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OCA) {

	var App = {
		initialize: function() {
			$('#app-navigation').removeClass('icon-loading');
			$('#app-content .container').removeClass('icon-loading');

			var collection = new OCA.CustomGroups.CustomGroupCollection();
			var view = new OCA.CustomGroups.CustomGroupsView(collection);
			view.render();

			collection.fetch();

			$('#app-content .container').append(view.$el);
		}
	};

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.App = App;

})(OCA);

$(document).ready(function() {
	OCA.CustomGroups.App.initialize();
});
