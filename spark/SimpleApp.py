from pyspark.sql import SparkSession
logFile = "D:/data/proj/sandbox/spark/data/battle_reports.csv"
spark = SparkSession.builder.appName("SimpleApp").getOrCreate()
logData = spark.read.text(logFile).cache()
numAs = logData.filter(logData.value.contains('a')).count()
numBs = logData.filter(logData.value.contains('b')).count()
print("\n================\n\nLines with a: %i, lines with b: %i\n================\n\n" % (numAs, numBs))
spark.stop()
