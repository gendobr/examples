from pyspark import SparkContext
from pyspark.streaming import StreamingContext
import json
import re

logDir = "file:///D:/data/proj/sandbox/spark/data/logs/" # the input directory, change this
sc = SparkContext("local[*]", "LogReader")
ssc = StreamingContext(sc, 10)


ssc.checkpoint("D:/data/proj/sandbox/spark/tmp") # the checkpoint directory, change this

lines = ssc.textFileStream(logDir)

linestart = re.compile(r'^\d{1,2} [A-Za-z]{3} \d{4} | \d{2}:\d{2}:\d{2},\d{3}', re.IGNORECASE)
linesplit = re.compile(r'\s*\|\s+')
log_item={}

log_line_key=0
log_line_num=0
def parse_log_line(line):
    global log_line_num
    global log_line_key
    log_line_num=log_line_num+1
    # start of the line found
    if linestart.match(line):
        log_line_key=log_line_key+1
        # parse line
        splitted_line=linesplit.split(line) 
        # create structure
        log_item={'date':splitted_line[0],  
                  'time': splitted_line[1],
                  'level': splitted_line[2],
                  'thread': splitted_line[3],
                  'label': splitted_line[4],
                  'message': [(log_line_num, splitted_line[6] )]
                 }
        
        log_line_value=json.dumps(log_item)
    else:
        log_line_value=json.dumps({'message': [( log_line_num, str(line) )] })
    return (log_line_key, log_line_value)

def line_join(line1, line2 ):
    json1=json.loads(line1)
    json2=json.loads(line2)
    line={}
    for k in json1:
        line[k]=json1[k]
    for k in json2:
        line[k]=json2[k]
    line['message']=json1['message']
    line['message'].extend(json2['message'])
    return json.dumps(line)

def message_join(line):
    json1=json.loads(line[1])
    message="".join([x[1] for x in sorted(json1['message'])])
    json1['message']=message
    return json1

def showRecord(rdd):
    print("\n\n\n\n============================= open ")
    rdd.foreach(lambda record: print("record", record))
    print("============================= close \n\n\n\n")

# logData = lines.map(parse_log_line).reduceByKey(line_join).map(message_join).saveAsTextFiles("D:/data/proj/sandbox/spark/data/streming-out/logs").foreachRDD(showRecord)
logData = lines.map(parse_log_line).reduceByKey(line_join).map(message_join).saveAsTextFiles("D:/data/proj/sandbox/spark/data/streming-out/logs")  # the output directory, change this

ssc.start()             # Start the computation
ssc.awaitTermination()  # Wait for the computation to terminate


