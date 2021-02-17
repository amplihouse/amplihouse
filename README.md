##### Requirements
- nodejs
- clickhouse

##### Installation

- `cd /opt`
- `git clone https://github.com/amplihouse/amplihouse.git`
- `cd amplihouse`
- `node utils/schema.js | clickhouse-client`
- change apiUrl in your sdk

##### Usage
- `node index.js`

##### Systemd autostart script
- `sudo cp amplihouse.service /usr/lib/systemd/system/amplihouse.service`
- `sudo systemctl daemon-reload && systemctl enable amplihouse && systemctl start amplihouse`

##### Troubleshooting
amplihouse writes unsent rows in unsentRowsDir when clickhouse is unavailable. amplihouse tries to send that again when clickhouse is available by command like:
- `cat unsent_rows.log | clickhouse-client --query "INSERT INTO amplihouse.events FORMAT JSONEachRow" && rm -f unsent_rows.log`

##### License
MIT License.

##### See also
- [nginxhouse](https://github.com/nginxhouse/nginxhouse)
