const fs = require('fs');

const schema = JSON.parse(fs.readFileSync('config/schema.json'));

function tableSql(table) {
    let columns = [];
    for (let column in schema[table]) {
        //console.log(schema[table][column].type);
        columns.push(`${schema[table][column].newName??column} ${schema[table][column].type}`);
    }
    return schema.tableTemplates[table].replace('%columns', columns.join(", "));
}

console.log('create database amplihouse;');
console.log(tableSql('events'));
console.log(tableSql('users'));
console.log(tableSql('sessions'));
