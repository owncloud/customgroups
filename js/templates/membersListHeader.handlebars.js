(function() {
  var template = Handlebars.template, templates = OCA.CustomGroups.Templates = OCA.CustomGroups.Templates || {};
templates['membersListHeader'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<input class=\"action-leave-group\" type=\"button\" value=\""
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"leaveGroupLabel") || (depth0 != null ? lookupProperty(depth0,"leaveGroupLabel") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"leaveGroupLabel","hash":{},"data":data,"loc":{"start":{"line":6,"column":55},"end":{"line":6,"column":74}}}) : helper)))
    + "\" />\n";
},"3":function(container,depth0,helpers,partials,data) {
    return "<div class=\"add-member-container\"></div>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div class=\"group-name-title\">\n	<span class=\"avatar\"></span>\n	<span class=\"group-name-title-display\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"groupName") || (depth0 != null ? lookupProperty(depth0,"groupName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"groupName","hash":{},"data":data,"loc":{"start":{"line":3,"column":40},"end":{"line":3,"column":53}}}) : helper)))
    + "</span>\n</div>\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"userIsMember") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":5,"column":0},"end":{"line":7,"column":7}}})) != null ? stack1 : "")
    + "<a class=\"close action-close icon-close\" href=\"#\" alt=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"closeLabel") || (depth0 != null ? lookupProperty(depth0,"closeLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"closeLabel","hash":{},"data":data,"loc":{"start":{"line":8,"column":55},"end":{"line":8,"column":69}}}) : helper)))
    + "\"></a>\n\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"canAdmin") : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":10,"column":0},"end":{"line":12,"column":7}}})) != null ? stack1 : "");
},"useData":true});
})();
