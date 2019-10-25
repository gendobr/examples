
# coding: utf-8

# In[1]:


# install Owlready2 package
get_ipython().system('pip install Owlready2')


# In[1]:


# load ontology
from owlready2 import *

# set cache path
onto_path.append("./pizza")

# load owl file
onto = get_ontology("./pizza/pizza.owl")

onto.load()


# In[2]:


# get namespace
pizza = onto.get_namespace('http://www.co-ode.org/ontologies/pizza/pizza.owl')


# In[3]:


sync_reasoner()


# In[4]:


"""
Show list of classes
"""
list(onto.classes())


# In[5]:


"""
Show list of properties
"""
list(onto.properties())


# In[6]:


"""
Search by IRI
"""

onto.search(iri = "*Topping")


# In[8]:


pizza.Pizza.descendants()


# In[9]:


list(onto.disjoint_classes())


# In[10]:


list(onto.different_individuals() )


# In[11]:


"""
install rdflib
"""

get_ipython().system('pip install rdflib')


# In[13]:


# construct SPARQL executor
graph = default_world.as_rdflib_graph()


# In[14]:


"""
Run simple query that shows properties of the class NamedPizza
?s                ?p                 ?o
rdf:about         some property      object
"""
list(graph.query("""
SELECT ?p ?o WHERE {
  <http://www.co-ode.org/ontologies/pizza/pizza.owl#NamedPizza> ?p ?o .
}"""))


# In[15]:


"""
Define xml namespaces
and run the same query
"""

list(graph.query("""
PREFIX foaf: <http://xmlns.com/foaf/0.1/>
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX terms: <http://purl.org/dc/terms/>
PREFIX owl:<http://www.w3.org/2002/07/owl#>
PREFIX xml:<http://www.w3.org/XML/1998/namespace>
PREFIX xsd:<http://www.w3.org/2001/XMLSchema#>
PREFIX skos:<http://www.w3.org/2004/02/skos/core#>
PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>
PREFIX dc:<http://purl.org/dc/elements/1.1/>
SELECT ?p ?o WHERE {
  pizza:NamedPizza ?p ?o .
}"""))


# In[16]:


"""
Query for class - direct_subclass relations
"""
list(graph.query("""
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT ?s ?o WHERE {
  ?s rdfs:subClassOf ?o .
}"""))


# In[20]:


"""
Look for all pizza names
"""
list(graph.query("""
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>
PREFIX owl:<http://www.w3.org/2002/07/owl#>
PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>
SELECT ?s  WHERE {
  ?s rdfs:subClassOf pizza:NamedPizza .
}"""))


# In[25]:


"""
Search all pizza descendants
"""
list(graph.query("""
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>
PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>

SELECT ?s  WHERE {
  ?s rdfs:subClassOf* pizza:Pizza .
}"""))


# In[26]:


"""
Search 
"""
list(graph.query("""
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>
PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>

SELECT ?s  WHERE {
  ?s rdfs:subClassOf pizza:VegetableTopping .
}"""))


# In[29]:


"""
Show all pizzas that have topping
"""
list(graph.query("""
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>

select distinct ?p where {
  ?p rdfs:subClassOf [
      a owl:Restriction;
      owl:onProperty pizza:hasTopping
  ] .
}"""))


# In[30]:


"""
Extend previous query with requirement "pizza should have vegetable topping"
"""
list(graph.query("""
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX pizza:<http://www.co-ode.org/ontologies/pizza/pizza.owl#>
select DISTINCT ?p where {
  ?p rdfs:subClassOf [
      a owl:Restriction;
      owl:onProperty pizza:hasTopping;
      owl:someValuesFrom ?t
  ] .
  ?t rdfs:subClassOf pizza:VegetableTopping
}"""))

