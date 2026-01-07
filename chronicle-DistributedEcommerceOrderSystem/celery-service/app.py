from flask import Flask, request, jsonify
from tasks import process_order

app = Flask(__name__)

@app.route('/')
def index():
    return {'message': 'Celery Service Running'}

@app.route('/tasks/order-processed', methods=['POST'])
def enqueue_order_task():
    data = request.get_json()
    
    if not data or 'order_id' not in data:
        return jsonify({'error': 'order_id is required'}), 400
    
    order_id = data['order_id']
    
    if not isinstance(order_id, int):
        return jsonify({'error': 'order_id must be an integer'}), 400
    
    process_order.delay(order_id)
    
    return jsonify({
        'message': 'Task queued',
        'order_id': order_id
    }), 202

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
