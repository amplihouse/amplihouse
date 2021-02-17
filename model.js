const fs = require('fs');
const clickhouse = require('./clickhouse.js').init();
const schema = JSON.parse(fs.readFileSync('config/schema.json'));

exports.createRow = createRow;

function createRow(row) {
    for (let metric in row) {
        if (!schema['events'][metric]) {
            delete row[metric];
        } else {
            if (schema['events'][metric].type.includes('Int')) {
                row[metric] = parseInt(row[metric]);
            } else if (schema['events'][metric].type.includes('Float')) {
                row[metric] = parseFloat(row[metric]);
            } else if (schema['events'][metric].type.includes('DateTime')) {
                let time;
                if (schema['events'][metric].sourceFormat && schema['events'][metric].sourceFormat === 'timestamp_ms') {
                    time = new Date(parseInt(row[metric]));
                } else {
                    time = new Date(row[metric]);
                }
                row[metric] = time.toISOString().slice(0, 19).replace('T', ' ');
            } else {
                row[metric] = row[metric].toString();
            }

            if (schema['events'][metric].replace && schema['events'][metric].replace) {
                let re = new RegExp(schema['events'][metric].replace.regexp);
                row[metric] = row[metric].replace(re, schema['events'][metric].replace.newSubstring);
            }

            if (schema['events'][metric].newName) {
                row[schema['events'][metric].newName] = row[metric];
                delete row[metric];
            }

            if (schema['events'][metric].ignoreList && schema['events'][metric].ignoreList.includes(row[metric])) {
                return;
            }
        }
    }

    clickhouse.addRow(row);
}
