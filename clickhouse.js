const fs = require('fs');
const http = require('http');
const url = require('url');
const zlib = require('zlib');
const child_process = require('child_process');
const cluster = require('cluster');
const config = JSON.parse(fs.readFileSync('config/clickhouse.json'));

exports.init = init;
exports.addRow = addRow;
//exports.sendRows = sendRows;
// exports.saveUnsentRows = saveUnsentRows;
// exports.saveUnsentRowsSync = saveUnsentRowsSync;

let lockForSending = false;
let clickhouseOptions;
let unsentRows = '';

function errorLog(error) {
    console.log(error);
    fs.appendFile(config.errorLog, `${new Date().toISOString()}: ${error}\n`, () => {});
}

function init() {
    clickhouseOptions = url.parse(config.url);
    clickhouseOptions.path += `&query=INSERT+INTO+${config.table}+FORMAT+JSONEachRow`;
    clickhouseOptions.method = 'POST';
    clickhouseOptions.headers = {'Content-Type': 'text/plain'};
    clickhouseOptions.timeout = config.timeout * 1000;

    process.on('SIGTERM', () => { //service nginxhouse stop
        saveUnsentRowsSync();
        process.exit(0);
    });

    process.on('SIGINT', () => { // ctrl + C
        saveUnsentRowsSync();
        process.exit(0);
    });

    //send unsent rows automatically

    setInterval(() => {
        if (cluster.isMaster) {
            if (!config.unsentRowsDir || lockForSending) return;
            lockForSending = true;

            child_process.exec(`find unsent_rows -type f -mmin +${1 + config.timer / 60} | while read i; do cat "$i" | curl  -H 'Content-Encoding: gzip' --data-binary @- '${config.url}&query=INSERT+INTO+${config.table}+FORMAT+JSONEachRow' && rm -f "$i"; done`, (error) => {
                //console.log(result);
                if (error) {
                    errorLog(error);
                }
                lockForSending = false;
            });
        } else if (unsentRows) {
            //console.log(rows);
            sendRows(unsentRows);
            unsentRows = '';
        }
    }, config.timer * 1000);

    return this;
}

function addRow(row) {
    unsentRows += JSON.stringify(row) + "\n";
}

function sendRows(rows) {
    if (lockForSending) {
        return false;
    } else {
        lockForSending = true;
    }

    zlib.gzip(rows, (_, rows) => {
        clickhouseOptions.headers['Content-Length'] = Buffer.byteLength(rows);
        clickhouseOptions.headers['Content-Encoding'] = 'gzip';
        const request = http.request(clickhouseOptions);

        //request.setNoDelay(true);

        request.on('response', (response) => {
            let data = '';
            response.on('data', function (chunk) {
                data += chunk;
            });
            response.on('end', () => {
                if (response.statusCode !== 200) {
                    errorLog(data !== '' ? data : 'clickhouse: response.statusCode !== 200');
                    saveUnsentRows(rows);
                }
                lockForSending = false;
            });
        });

        request.on('error', (error) => {
            errorLog(error);
            saveUnsentRows(rows);
            lockForSending = false;
        });

        request.on('timeout', () => {
            request.abort();
        });

        request.write(rows);
        request.end();
    });
}

function saveUnsentRows(rows) {
    //console.log(rows);
    if (!rows || !config.unsentRowsDir) return;

    fs.writeFile(`${config.unsentRowsDir}/${new Date().toISOString()}_${process.pid}.gz`, rows, (error) => {if (error) {errorLog(error);}});
    unsentRows = '';
}

function saveUnsentRowsSync() {
    //console.log(rows);
    if (!unsentRows || !config.unsentRowsDir) return;

    fs.writeFileSync(`${config.unsentRowsDir}/${new Date().toISOString()}_${process.pid}.gz`, zlib.gzipSync(unsentRows));
    unsentRows = '';
}
