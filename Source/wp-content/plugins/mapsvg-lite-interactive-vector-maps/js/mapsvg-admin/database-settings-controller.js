(function($, window){
    var MapSVGAdminDatabaseSettingsController = function(container, admin, mapsvg){
        this.name = 'database-settings';
        MapSVGAdminController.call(this, container, admin, mapsvg);
    };
    window.MapSVGAdminDatabaseSettingsController = MapSVGAdminDatabaseSettingsController;
    MapSVG.extend(MapSVGAdminDatabaseSettingsController, window.MapSVGAdminController);



})(jQuery, window);