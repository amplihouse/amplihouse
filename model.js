const fs = require('fs');
const clickhouse = require('./clickhouse.js').init();
const schema = JSON.parse(fs.readFileSync('config/schema.json'));

exports.createRow = createRow;

function createRow(row) {
    for (let metric in row) {
        let metricParams = schema.raw.columns[metric];
        if (!metricParams) {
            delete row[metric];
        } else {
            if (metricParams.type.includes('Int')) {
                row[metric] = parseInt(row[metric]);
            } else if (metricParams.type.includes('Float')) {
                row[metric] = parseFloat(row[metric]);
            } else if (metricParams.type.includes('DateTime')) {
                let time;
                if (metricParams.sourceFormat && metricParams.sourceFormat === 'timestamp_ms') {
                    time = new Date(parseInt(row[metric]));
                } else {
                    time = new Date(row[metric]);
                }
                row[metric] = time.toISOString().slice(0, 19).replace('T', ' ');
            } else {
                row[metric] = row[metric].toString();
            }

            if (metricParams.replace && metricParams.replace) {
                let re = new RegExp(metricParams.replace.regexp);
                row[metric] = row[metric].replace(re, metricParams.replace.newSubstring);
            }

            if (metricParams.newName) {
                row[metricParams.newName] = row[metric];
                delete row[metric];
            }

            if (metricParams.ignoreList && metricParams.ignoreList.includes(row[metric])) {
                return;
            }
        }
    }

    clickhouse.addRow(row);
}
