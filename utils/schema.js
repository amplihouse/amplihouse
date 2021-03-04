const fs = require('fs');

const schema = JSON.parse(fs.readFileSync('config/schema.json'));

function sql(table) {
    let tableConfig = schema[table];
    let columns = [];
    let queries = [];

    for (let column in tableConfig.columns) {
        let columnParams = tableConfig.columns[column];
        let columnString, queryString;

        if (tableConfig.type === 'table') {
            columnString = `${columnParams.newName??column} ${columnParams.type}`;
            columns.push(columnString);
        } else if (tableConfig.type === 'matview') {
            queryString = `${column}`;
            if (columnParams.query) {
                queryString = `${columnParams.query} as ${column}`;
            }
            //columnString = `${column} ${schema[tableConfig.tableSchema].columns[column].type}`;
        } else if (tableConfig.type === 'view') {
            queryString = `${column}`;
            if (columnParams.query) {
                queryString = `${columnParams.query} as ${column}`;
            }
        }

        queries.push(queryString);
    }

    return tableConfig.template.replace('%columns', "\n  "+columns.join(",\n  ")+"\n").replace('%queries', "\n  "+queries.join(",\n  ")+"\n")+"\n";
}

console.log('--drop database amplihouse;');

console.log('create database amplihouse;');

for (let i in schema) {
    console.log(sql(i));
}