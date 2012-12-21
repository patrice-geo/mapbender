(function($) {

$.widget("mapbender.mbScaleSelector", {
    options: {
        target: null
    },

    elementUrl: null,

    _create: function() {
        var self = this;
        var me = $(this.element);
//        this.elementUrl = Mapbender.configuration.elementPath + me.attr('id') + '/';
         $(document).one('mapbender.setupfinished', function() {
            $('#' + self.option.target).mbMap('ready', $.proxy(self._init, self));
        });
    },
    
    _init: function(){
        if(this.options.target){
            var mbMap = $('#' + this.options.target).data('mbMap');
            var scale = mbMap.map.olMap.getScale();
            var scales = mbMap.scales();
            var html = '';
            $.each(scales, function(idx, val) {
                val = Math.round(val);
                html += '<option value="' + val + '">' + val + '</option>';
            });
            $("#"+$(this.element).attr('id')+" select").html(html);
            $("#"+$(this.element).attr('id')+" select").change($.proxy(this._zoomToScale, this));
            $("#"+$(this.element).attr('id')+" select").val(scale);
            mbMap.map.olMap.events.register('zoomend', this, $.proxy(this._updateScale, this));
        }
    },
    
    _zoomToScale: function(){
        var scale = $("#"+$(this.element).attr('id')+" select").val();
        var map = $('#' + this.options.target).data('mbMap');
        map.zoomToScale(scale);
    },
    
    _updateScale: function(){
        var map = $('#' + this.options.target).data('mbMap');
        var scale = Math.round(map.map.olMap.getScale());
        $("#"+$(this.element).attr('id')+" select").val(Math.round(scale));
    },

    _destroy: $.noop
});

})(jQuery);

