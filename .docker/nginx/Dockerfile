FROM nginx:1.21-alpine

ENV fastcgi_pass "php:9000"
ENV client_max_body_size "100M"
ENV root "/srv/web"
ENV index "index.php"
ENV index_regex "index\.php"

ADD ./default.conf /etc/nginx/conf.d/default.template

CMD sh -c "envsubst \"`env | awk -F = '{printf \" \\\\$%s\", $1}'`\" < /etc/nginx/conf.d/default.template > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'"
