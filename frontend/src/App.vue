<template>
  <div id="app">
    <div class="container">
      <h1>Информация etherium</h1>
      <hr />
      <div class="panel panel-default">
      <h3>Блоки - ({{ blocks_counter }} штук)</h3>
      <b-table striped hover :items="blocks"></b-table>
      <hr />
      </div>
      <div class="panel panel-default">
      <h3>Транзакции - ({{ transaction_counter }} штук)</h3>
      <b-table striped hover :items="transactions"></b-table>
      <hr />
      </div>
    </div>
  </div>
</template>

<script>
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'

export default {
  name: 'app',
  created () {
    let that = this
    let url = process.env.VUE_APP_WS_URL.length > 0 ? process.env.VUE_APP_WS_URL : "ws://localhost:8080/infura";
    this.ws = new WebSocket(url);
    this.ws.onopen = function() {
      console.log('WS подключенно')
    };
    this.ws.onclose = function(eventclose) {
      console.log('соеденение закрыто причина: ' + eventclose)
    }
    this.ws.onmessage = function(msg) {
      let data = JSON.parse(msg.data);
      if (data.channel === "blocks") {
        that.addBlock(data.data)
      } else if (data.channel === "transactions") {
        that.addTransaction(data.data)
      }
    }
  },
  data: function () {
    return {
      ws: null,
      blocks: [],
      blocks_counter: 0,
      transactions: [],
      transaction_counter:0,
    };
  },
  methods: {
    addBlock (block) {
      if (this.blocks.length >= 5) {
        this.blocks.shift()
      }
      this.blocks.push({
        "number": block.number,
        "hash": block.hash,
      });
      this.blocks_counter++;

      console.log(block)
    },
    addTransaction (transaction) {
      if (this.transactions.length >= 10) {
        this.transactions.shift()
      }
      this.transactions.push({
        "hash": transaction.hash,
        "from": transaction.from,
        "to": transaction.to,
      });
      this.transaction_counter++;
      console.log(transaction)
    },
  },
}
</script>
