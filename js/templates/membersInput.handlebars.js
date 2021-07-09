(function() {
  var template = Handlebars.template, templates = OCA.CustomGroups.Templates = OCA.CustomGroups.Templates || {};
templates['membersInput'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div>\n	<input class=\"member-input-field\" type=\"text\" placeholder=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"placeholderText") || (depth0 != null ? lookupProperty(depth0,"placeholderText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"placeholderText","hash":{},"data":data,"loc":{"start":{"line":2,"column":60},"end":{"line":2,"column":79}}}) : helper)))
    + "\" />\n	<span class=\"loading icon-loading-small hidden\"></span>\n</div>\n<br><br>\n<div>\n    <textarea id=\"bulk-users-list\" class=\"bulk-input-field\" rows=\"25\" placeholder=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"addBulkUsersText") || (depth0 != null ? lookupProperty(depth0,"addBulkUsersText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"addBulkUsersText","hash":{},"data":data,"loc":{"start":{"line":7,"column":83},"end":{"line":7,"column":103}}}) : helper)))
    + "\"></textarea>\n    <span class=\"loading-bulk icon-loading-small hidden\"></span><br>\n    <input class=\"action-add-bulk\" type=\"button\" value=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"addBulkLabel") || (depth0 != null ? lookupProperty(depth0,"addBulkLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"addBulkLabel","hash":{},"data":data,"loc":{"start":{"line":9,"column":56},"end":{"line":9,"column":72}}}) : helper)))
    + "\" />\n</div>\n";
},"useData":true});
})();
