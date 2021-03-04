const fs = require('fs');
const http = require('http');
const url = require('url');
const querystring = require('querystring');
const cluster = require('cluster');

const config = JSON.parse(fs.readFileSync('config/server.json'));
const model = require('./model.js');

if (cluster.isMaster) {
    if (!config.forks) {
        config.forks = os.cpus().length;
    }

    // Fork workers.
    for (let i = 0; i < config.forks; i++) {
        cluster.fork();
    }
} else {
    const server = http.createServer((req, res) => {
        const urlObject = url.parse(req.url, true);
        // res.setHeader('Content-Type', 'application/json');
        //res.setHeader('Connection', 'keep-alive');
        //const params = urlObject.query;//return res.end('error');
        if (!['/'].includes(urlObject.pathname) || req.method !== 'POST') return res.end('wrong path or method');

        let body = '';
        req.on('data', function (data) {
            body += data;
        });
        req.on('end', function () {
            let events;
            try {
                body = querystring.parse(body);
                events = JSON.parse(body.e);
            } catch(e) {
                return res.writeHead(400).end('error while parsing http request body');
            }

            for (let i in events) {
                events[i]['country'] = req.headers.geoip_country_name ?? '';
                events[i]['country_code'] = req.headers.geoip_country_code ?? '';
                events[i]['region'] = req.headers.geoip_region_name ?? '';
                events[i]['city'] = req.headers.geoip_city_name ?? '';
                model.createRow(events[i]);
            }

            return res.end();
        });

        //console.log(rows);

    }).listen(config.port, config.host);
}
