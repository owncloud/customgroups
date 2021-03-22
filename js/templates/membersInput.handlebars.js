(function() {
  var template = Handlebars.template, templates = OCA.CustomGroups.Templates = OCA.CustomGroups.Templates || {};
templates['membersInput'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div>\n" +
    "<input class=\"member-input-field\" type=\"text\" placeholder=\""
        + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"placeholderText") || (depth0 != null ? lookupProperty(depth0,"placeholderText") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"placeholderText","hash":{},"data":data,"loc":{"start":{"line":2,"column":60},"end":{"line":2,"column":79}}}) : helper)))
        + "\" />\n" +
    "<span class=\"loading icon-loading-small hidden\"></span>\n" +
    "<br><br><div>\n" +
    "<textarea id=\"bulk-users-list\" class=\"bulk-input-field\" rows=\"25\" placeholder=\"" +
      container.escapeExpression(((helper = (helper = lookupProperty(helpers,"addBulkUsersText") || (depth0 != null ? lookupProperty(depth0,"addBulkUsersText") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"addBulkUsersText","hash":{},"data":data,"loc":{"start":{"line":2,"column":60},"end":{"line":2,"column":79}}}) : helper))) +
    "\"></textarea><span class=\"loading-bulk icon-loading-small hidden\"></span><br>\n" +
    "<input class=\"action-add-bulk\" type=\"button\" value=\"" +
        container.escapeExpression(((helper = (helper = lookupProperty(helpers,"addBulkLabel") || (depth0 != null ? lookupProperty(depth0,"addBulkLabel") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"addBulkLabel","hash":{},"data":data,"loc":{"start":{"line":6,"column":55},"end":{"line":6,"column":74}}}) : helper))) +
        "\" />\n" +
    "</div>\n" +
    "</div>\n\n";
},"useData":true});
})();
