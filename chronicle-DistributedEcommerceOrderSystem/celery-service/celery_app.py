from celery import Celery

celery_service = Celery(
    'celery_service',
    broker='redis://redis:6379/0',
    backend='redis://redis:6379/0'
)

import tasks
