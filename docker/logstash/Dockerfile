FROM docker.elastic.co/logstash/logstash:5.4.0
RUN rm -f /usr/share/logstash/pipeline/logstash.conf
COPY pipeline/ /usr/share/logstash/pipeline/
#ADD config/ /usr/share/logstash/config/