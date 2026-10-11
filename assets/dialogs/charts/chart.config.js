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
                formatter: function (params) {
                    return '<b>' + params.name + '</b>: ' +
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
 * @param extend   浅合并函数（对话框传 $.extend，前台传 utils.extend），签名均为 (target, ...sources)。
 */
function buildChartOption ( typeItem, data, extend ) {

    var series = [],
        i, cur;

    for ( i = 0; cur = data.series[ i ]; i++ ) {

        //ECharts 要求每条 series 都显式携带 type；typeItem.series 承载该类型的专属默认值
        series.push( extend( {
            type: typeItem.seriesType
        }, typeItem.series || {}, cur ) );

    }

    return {
        //ECharts 无 Highcharts 的 credits/exporting 概念，无需禁用项
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
        legend: ( data.title || data.subTitle || data.suffix ) ? {
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
            name: data.yTitle || ''
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
