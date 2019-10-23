from pyspark.sql import SparkSession
logFile = "D:/data/proj/sandbox/spark/data/battle_reports.csv"
spark = SparkSession \
    .builder \
    .appName("Python Spark SQL basic example") \
    .config("spark.some.config.option", "some-value") \
    .getOrCreate()

# spark is an existing SparkSession
df = spark.read.json(logFile)
print("\n\n=====================\n\n")
df.printSchema()

#=====================
#
#
#root
# |-- _corrupt_record: string (nullable = true)
# |-- ihb: boolean (nullable = true)
# |-- killer_unit_param_id: long (nullable = true)
# |-- map: string (nullable = true)
# |-- unit_param_id: long (nullable = true)
# |-- user_alliance: string (nullable = true)
# |-- user_name: string (nullable = true)
# |-- x: long (nullable = true)
# |-- y: long (nullable = true)


print("\n\n=====================\n\n")
print("select distinct user_name ")
df.select(df['user_name']).distinct().show()


#+------------+
#|   user_name|
#+------------+
#|       fuchs|
#|      rela41|
#|  andretest4|
#|   max-power|
#|        foxy|
#|     djbomba|
#|       jan76|
#|     neuge21|
#|blackpanther|
#|         ttt|
#|    omex1985|
#|      spacer|
#|     rodgard|
#|     schakal|
#|  roland1984|
#|        bugg|
#|   schorscho|
#|  badboy2087|
#|      gnom11|
#|     l39tayo|
#+------------+
#only showing top 20 rows


print("\n\n=====================\n\n")
print("select distinct user_alliance ")
df.select(df['user_alliance']).distinct().show()


#+---------------+
#|  user_alliance|
#+---------------+
#|tester-alliance|
#|     Timberclub|
#|           ANTS|
#|     Nebee_only|
#|          simon|
#|     0_tutorial|
#|            new|
#|           GAIA|
#|       Warriors|
#|           null|
#|         Gino16|
#|      KILLERTNK|
#|       Friedhof|
#|  test3alliance|
#|       tutorial|
#|  gjDjg_ghj0jg1|
#|      steinersk|
#|           FAIR|
#|            BWT|
#|           Gino|
#+---------------+
#only showing top 20 rows

print("\n\n=====================\n\n")
print("select not tutorial map")
df.filter(df['map']!='tutorial').show()


#+---------------+----+--------------------+---+-------------+-------------+--------------+-----+-----+
#|_corrupt_record| ihb|killer_unit_param_id|map|unit_param_id|user_alliance|     user_name|    x|    y|
#---------------+----+--------------------+---+-------------+-------------+--------------+-----+-----+
# |          null|null|                null|  8|         null|         null|Neutral player|25715|20897|
# |          null|null|                null|  8|         null|         null|Neutral player|27297|24139|
# |          null|null|                null|  1|         null|         null|Neutral player|75234| 5666|
# |          null|null|                null|  1|         null|         null|Neutral player|74940| 5667|
# |          null|null|                null|  1|         null|         null|Neutral player|75201| 5666|
# |          null|null|                null|  1|         null|         null|Neutral player|74972| 5668|
# |          null|null|                null|  1|         null|         null|Neutral player|75166| 5666|
# |          null|null|                null|  1|         null|         null|Neutral player|75003| 5667|
# |          null|null|                null|  1|         null|         null|Neutral player|75134| 5667|
# |          null|null|                null|  1|         null|         null|        victor|74951| 5950|
# |          null|null|                null|  1|         null|         null|Neutral player|75034| 5668|
# |          null|null|                null|  1|         null|         null|Neutral player|57356|13863|
# |          null|null|                null|  1|         null|         null|Neutral player|75751|10142|
# |          null|null|                null|  1|         null|         null|Neutral player|75959|10037|
# |          null|null|                null|  1|         null|         null|Neutral player|75774|10128|
# |          null|null|                null|  1|         null|         null|Neutral player|75933|10051|
# |          null|null|                null|  1|         null|         null|Neutral player|75807|10111|
# |          null|null|                null|  1|         null|         null|        victor|76081|10276|
# |          null|null|                null|  1|         null|         null|Neutral player|75908|10066|
# |          null|null|                null|  1|         null|         null|Neutral player|75834|10097|
# +---------------+----+--------------------+---+-------------+-------------+--------------+-----+-----+
# only showing top 20 rows




print("\n\n=====================\n\n")
print("count most aggressive alliances")
df.filter(df['map']!='tutorial') \
  .groupBy('user_alliance') \
  .count() \
  .withColumnRenamed("count","nAttacks") \
  .orderBy(['nAttacks'], ascending=[0]) \
  .show()

# +---------------+--------+
# |  user_alliance|nAttacks|
# +---------------+--------+
# |       EROBERER|  107251|
# |           GAIA|   92094|
# |           null|   40423|
# |          GSG-9|   21550|
# |      DESTROYER|   12538|
# |            SEK|   11687|
# |           FAIR|    9835|
# |            BWT|    5915|
# |  gjDjg_ghj0jg1|    2101|
# |           ANTS|    1048|
# |        VICTORY|     993|
# |       TESTBEST|     780|
# |        geilomt|     729|
# |         Gino16|     675|
# |     Nebee_only|     369|
# |tester-alliance|     333|
# |      steinersk|     317|
# |       Friedhof|     234|
# |     Timberclub|     203|
# |           TEST|     152|
# +---------------+--------+
# only showing top 20 rows

print("\n\n=====================\n\n")
spark.stop()
