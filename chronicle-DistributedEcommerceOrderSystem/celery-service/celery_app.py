from celery import Celery

celery_service = Celery(
    'celery_service',
    broker='redis://redis:6379/0',
    backend='redis://redis:6379/0'
)

celery_service.conf.update(
    task_serializer='json',
    accept_content=['json'],
    result_serializer='json',
    timezone='UTC',
    enable_utc=True,
)

celery_service.autodiscover_tasks(['tasks'])
