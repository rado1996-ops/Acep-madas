!function(e,t){var m=window.wp.element.createElement;e.registerBlockType("mmp/map-shortcode",{title:"Maps Marker Pro",icon:m("img",{src:mmpGbVars.iconUrl}),category:"common",attributes:{id:{type:"string",default:"0"}},edit:function(t){for(var e=[m("option",{value:0},mmpGbL10n.select)],a=0;a<mmpGbVars.maps.length;a++){var r=mmpGbVars.maps[a],s=m("option",{value:r.id},r.name);e.push(s)}return m("div",{className:t.className},[m("p",{},mmpGbL10n.selectMap),m("select",{value:t.attributes.id,onChange:function(e){t.setAttributes({id:e.target.value})}},e)])},save:function(e){return"["+mmpGbVars.shortcode+' map="'+e.attributes.id+'"]'}})}(window.wp.blocks);