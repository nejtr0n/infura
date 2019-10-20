# Infura
вывод информации по блокам/транзакциям etherium

## Структура
* backend/pkg/infura.php - парсинг информации с https://infura.io
* backend/pkg/server.php - ws сервер для отдачи информации в браузер
* redis - pub/sub шина данных
* frontend - ws клиент для вывода информации в браузер