from celery_app import celery_service
import time

@celery_service.task(name='process_order')
def process_order(order_id):
    time.sleep(5)
    print(f"Order #{order_id} Processed.")
    return f"Order #{order_id} Processed."
