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
