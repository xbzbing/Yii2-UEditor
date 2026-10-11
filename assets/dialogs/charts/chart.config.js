/*
 * 图表配置文件（ECharts 5.x 版本）
 *
 * 两种使用方式共享同一份 typeConfig：
 *  - 编辑区上方预览（ueditor.parse.js 的 renderChart）
 *  - 图表对话框中的预览（assets/dialogs/charts/charts.js 的 renderCharts）
 *
 * seriesType 字段承载图表类型，由调用方在构造 series 时逐条注入 type。
 * 它是本文件的私有约定，不会透传给 ECharts。
 * */

//不同类型的配置
var typeConfig = [
    {
        // 折线图，不显示数据标签，允许鼠标跟踪
        seriesType: 'line'
    }, {
        // 折线图，显示数据标签，关闭鼠标跟踪
        seriesType: 'line',
        series: {
            label: {
                show: true
            },
            emphasis: {
                disabled: true
            }
        }
    }, {
        // 面积图
        seriesType: 'line',
        series: {
            areaStyle: {}
        }
    }, {
        // 条形图（横向）：与其余类型相反，x 轴为数值轴、y 轴为类目轴
        seriesType: 'bar',
        xAxis: {
            type: 'value'
        },
        yAxis: {
            type: 'category'
        }
    }, {
        // 柱状图（纵向，ECharts bar 的默认方向）
        seriesType: 'bar'
    }, {
        // 饼图
        seriesType: 'pie',
        series: {
            selectedMode: 'single',
            label: {
                color: '#000000',
                //加粗类目名用 ECharts 富文本标记：canvas label 不解析 HTML，直接写 <b> 会被当字面量画出
                rich: {
                    b: {
                        fontWeight: 'bold'
                    }
                },
                formatter: function (params) {
                    return '{b|' + params.name + '}: ' +
                        (Math.round(params.percent * 100) / 100) + ' %';
                }
            }
        }
    }
];

/**
 * 组装 ECharts option。
 *
 * 两个消费方——对话框预览（charts.js 的 buildOption）与前台渲染（ueditor.parse.js
 * 的 renderChart）——共用此函数，避免 option 结构在两处各写一份而逐渐漂移。
 *
 * @param typeItem typeConfig 中的某一项，含 seriesType，可选 series / xAxis / yAxis。
 * @param data     已归一的数据：{ series, categories, title, subTitle, xTitle, yTitle, suffix }。
 */
function buildChartOption ( typeItem, data ) {

    //自带浅合并，避免依赖 $.extend / utils.extend（后者的 this 绑定在跨文件传递时会丢失）
    function assign () {
        var target = {};
        for ( var i = 0; i < arguments.length; i++ ) {
            var source = arguments[ i ];
            for ( var key in source ) {
                if ( source.hasOwnProperty( key ) ) {
                    target[ key ] = source[ key ];
                }
            }
        }
        return target;
    }

    var series = [],
        i, cur;

    for ( i = 0; cur = data.series[ i ]; i++ ) {

        //ECharts 要求每条 series 都显式携带 type；typeItem.series 承载该类型的专属默认值
        series.push( assign( {
            type: typeItem.seriesType
        }, typeItem.series || {}, cur ) );

    }

    return {
        //ECharts 无 Highcharts 的 credits/exporting 概念，无需禁用项
        //关闭入场动画：对话框里每次改数据都会整体重建，动画会反复重放；展示页也无需入场动效
        animation: false,
        title: {
            text: data.title || '',
            subtext: data.subTitle || ''
        },
        tooltip: {
            trigger: 'item',
            formatter: function ( params ) {

                var suffix = data.suffix || '',
                    //饼图的 value 是数据项本身，取 params.value 需兼容 [name, value] 结构
                    value = params.value instanceof Array
                        ? params.value[ 1 ]
                        : params.value;

                return params.name + ': ' + value + suffix;

            }
        },
        legend: ( data.series && data.series.length > 0 ) ? {
            orient: 'vertical',
            right: 0,
            top: 'middle',
            borderWidth: 1
        } : false,
        xAxis: data.categories ? {
            //条形图为横向，x 轴为数值轴（类型已由 typeItem.xAxis 指定）
            type: typeItem.xAxis ? typeItem.xAxis.type : 'category',
            name: data.xTitle || '',
            data: typeItem.xAxis ? undefined : data.categories
        } : undefined,
        yAxis: data.categories ? {
            type: typeItem.yAxis ? typeItem.yAxis.type : 'value',
            name: data.yTitle || '',
            //类目轴（横向条形图的 y 轴）必须显式携带类目名，否则 ECharts 用序列下标 0/1/2
            data: typeItem.yAxis ? data.categories : undefined
        } : undefined,
        grid: {
            //为轴标题留出空间，否则会与坐标轴标签重叠
            top: 40,
            left: 10,
            right: 90,
            bottom: 30
        },
        series: series
    };

}
