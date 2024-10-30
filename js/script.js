(function($){

    var google_visualization_loaded = false;

    google.load("visualization", "1", {packages:["corechart"]});
    google.setOnLoadCallback(function(){

        google_visualization_loaded = true;

        $.each( widgetsWaitingForLoad , function( i , widget ){

            $( widget.element ).litecoinWidget( widget.data );

        });

    });

    var listOfWidgets = new Array();
    var widgetsWaitingForLoad = new Array();

    var widgetID = 0;

    

    $.fn.litecoinWidget = function( data ){

        if( !google_visualization_loaded ){

            return this.each(function(){

                widgetsWaitingForLoad.push({ "element" : this, "data" : data });

            });
        }

        return this.each(function(){

            var $widget = $(this),
                $tab_links = $widget.find("a.litecoin-tab-link"),
                $tabs = $widget.find(".litecoin-tab"),
                ID = widgetID++;

            listOfWidgets.push( $widget );

            if( listOfWidgets.length == 1 ){
                setInterval(function(){

                    $.get( lcw_ajax_url , { action : "lcw_data" , random : new Date().getTime() }, function( response ){

                        $.each( listOfWidgets , function( i , widget ){

                            $(widget).trigger("lcw.update",[ response ]);

                        });

                    },"json");

                }, 1 * 60 * 1000 );
            }

            $tab_links.each(function( index ){

                var $tab_link = $(this),
                    $tab = $tabs.eq(index),
                    tabName = $tab.attr("id").replace("litecoin-tab-",""),
                    tabData = data[tabName],
                    $time_links =  $tab.find(".litecoin-login-status a"),
                    time = null;

               $time_links.bind("click",function(e){

                    e.preventDefault();

                    $time_links.removeClass("active");

                    $(this).addClass("active");

                    time = $(this).data("time");

                    $tab.data("time",time);

                    $tab.find(".litecoin-chart").empty();

                    if( !tabData["chart"] || !tabData["chart"][ time ] || tabData["chart"][ time ].length == 0 ){
                        $tab.find(".litecoin-chart").addClass("litecoin-chart-disabled").html( "<span>Data currently not available</span>" );
                    }
                    else {
                        
                        var chartData = new Array();

                        chartData.push(['','']);

                        $.each( tabData["chart"][ time ] , function( index , time_data ){

                            chartData.push( [ null, time_data[ 1 ] ] );

                        } );

                        var googleChartData = google.visualization.arrayToDataTable(chartData);

                        var options = {
                            hAxis : {
                                textPosition : "none"
                            },
                            legend : {
                                position : "none"
                            }
                        };

                        var chart = new google.visualization.LineChart( $tab.find(".litecoin-chart").get( 0 ) );

                        chart.draw(googleChartData, options);

                        $(window).unbind("resize.litecoin"+ID).bind("resize.litecoin"+ID,function(){

                            chart.draw(googleChartData, options);

                        });

                    }

                });

                $tab_link.bind("click",function(e){

                    e.preventDefault();

                    $tab_links.removeClass("active");

                    $tab_link.addClass("active");

                    $tabs.hide();

                    $tab.show();

                    $time_links.filter(".active").trigger("click");

                });

                $tab.data("time","daily");

            }).first().trigger("click");

            $widget.bind("lcw.update",function( e, new_data ){
                data = new_data;

                $tabs.each(function(){

                    var $tab = $(this),
                        tabName = $tab.attr("id").replace("litecoin-tab-",""),
                        tabData = data[tabName];

                    $tab.find(".litecoin-last-price").html(' <h2>$'+(number_format(tabData.ticker.buy,2))+'</h2>');

                    $tab.find(".litecoin-data").html(
                        '<ul>\
                            <li>Buy : $'+ (number_format(tabData.ticker.buy,2))+'</li>\
                            <li>Sell : $'+(number_format(tabData.ticker.sell,2))+'</li>\
                            <li>High : $'+(number_format(tabData.ticker.high,2))+'</li>\
                            <li>Low : $'+(number_format(tabData.ticker.low,2))+'</li>\
                            <li>Volume : '+(number_format(tabData.ticker.vol_cur,0))+' LTC</li>\
                        </ul>'
                    );

                    $tab.find('.litecoin-timeago').livestamp('destroy').livestamp( data.updated );
                        
                    $tab.find(".litecoin-chart").empty();

                    $tab.removeClass("litecoin-tab-loading");

                 //   if( tabData["chart"][ $tab.data("time") ].length == 0 ){
				    if( tabData["chart"][ $tab.data("time") ].length == 0 ){
                        $tab.find(".litecoin-chart").html( "Data currently not available" );
                    }
                    else {

                        var chartData = new Array();

                        chartData.push(['','']);

                        $.each( tabData["chart"][ $tab.data("time") ] , function( index , time_data ){

                            chartData.push( [ null, time_data[ 1 ] ] );

                        } );

                        var googleChartData = google.visualization.arrayToDataTable(chartData);

                        var options = {
                            hAxis : {
                                textPosition : "none"
                            },
                            legend : {
                                position : "none"
                            }
                        };

                        var chart = new google.visualization.LineChart( $tab.find(".litecoin-chart").get( 0 ) );

                        chart.draw(googleChartData, options);

                        $(window).unbind("resize.litecoin"+ID).bind("resize.litecoin"+ID,function(){

                            chart.draw(googleChartData, options);
                            
                        });

                    }

                });

            });

            $tabs.find('.litecoin-timeago').livestamp( data.updated );

        });
    }

})(jQuery);

